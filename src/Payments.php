<?php

declare(strict_types=1);

namespace rapttor\payment;

use rapttor\payment\dto\CheckoutRequest;
use rapttor\payment\dto\CheckoutSession;
use rapttor\payment\models\Discount;
use rapttor\payment\models\Order;
use rapttor\payment\models\Price;
use rapttor\payment\models\Provider;
use rapttor\payment\providers\ProviderInterface;
use rapttor\payment\services\Processor;
use rapttor\payment\services\WalletService;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\Url;

/**
 * Entry point: `Yii::$app->payments`.
 *
 *   $session = Yii::$app->payments->checkout('coins-500', 'stripe', Yii::$app->user->id);
 *   return $this->redirect($session->redirectUrl);
 *
 * Config (credentials from env, never from the database):
 *   'payments' => ['class' => rapttor\payment\Payments::class, 'providers' => [
 *       'stripe' => ['class' => rapttor\payment\providers\StripeProvider::class, 'secretKey' => getenv('STRIPE_SECRET_KEY'), ...],
 *   ]],
 */
final class Payments extends Component
{
    public const EVENT_ORDER_PAID = 'orderPaid';
    public const EVENT_ORDER_REFUNDED = 'orderRefunded';
    public const EVENT_SUBSCRIPTION_RENEWED = 'subscriptionRenewed';

    /** @var array<string,array|ProviderInterface> id => provider config */
    public array $providers = [];
    /** URL prefix and controller id of the package routes. */
    public string $routePrefix = 'payments';
    /** Where buyers land after paying or cancelling. `order` and `status` query params are appended. */
    public string|array $returnUrl = '/';
    /**
     * Public base URL of the FRONTEND (e.g. https://shop.example.com or your tunnel). Webhook and return URLs are built from it,
     * so they are right even when the backoffice runs on another host. Null = the current request's host.
     */
    public ?string $publicUrl = null;
    /**
     * Short slug of the app that owns this catalog (e.g. `cetirizida`). Sent to providers as `custom_data.app` on products, prices
     * and transactions, so one provider account can serve many apps; webhooks tagged for another app are ignored.
     */
    public string $app = '';
    /** Let visitors who are not logged in buy (orders get user_id null, no wallet credit). */
    public bool $allowGuests = false;

    /** @var array<string,ProviderInterface> */
    private array $instances = [];
    private ?WalletService $wallet = null;

    public function provider(string $id): ProviderInterface
    {
        if (!isset($this->instances[$id])) {
            $cfg = $this->providers[$id] ?? throw new InvalidConfigException("Unknown payment provider '$id'.");
            $this->instances[$id] = $cfg instanceof ProviderInterface ? $cfg : Yii::createObject($cfg + ['app' => $this->app]);
        }

        return $this->instances[$id];
    }

    /** @return string[] ids of every configured provider class, enabled or not */
    public function providerIds(): array
    {
        return array_keys($this->providers);
    }

    /** Switched on in the backoffice AND has its credentials (the fake sandbox is refused in production). */
    public function isEnabled(string $id): bool
    {
        if (!isset($this->providers[$id]) || ($id === 'sandbox' && YII_ENV_PROD)) {
            return false;
        }
        $row = Provider::findOne($id);

        return $row !== null && (bool) $row->enabled && $this->provider($id)->isConfigured();
    }

    /**
     * Providers a buyer may choose for this currency / country / capability, in backoffice order.
     *
     * @return ProviderInterface[]
     */
    public function available(?string $currency = null, ?string $country = null, ?string $capability = null): array
    {
        $out = [];
        $rows = Provider::find()->where(['enabled' => 1])->orderBy(['sort' => SORT_ASC, 'code' => SORT_ASC])->all();
        foreach ($rows as $row) {
            if (!$this->isEnabled($row->code)) {
                continue;
            }
            $p = $this->provider($row->code);
            $cur = Provider::list($row->currencies);
            $cty = Provider::list($row->countries);
            if (($capability && !in_array($capability, $p->capabilities(), true))
                || ($currency && $cur && !in_array(strtoupper($currency), $cur, true))
                || ($country && $cty && !in_array(strtoupper($country), $cty, true))) {
                continue;
            }
            $out[] = $p;
        }

        return $out;
    }

    /** Price after an optional coupon. @return array{amount:int, discount:int, discountModel:?Discount} */
    public function quote(Price $price, ?string $discountCode = null): array
    {
        $d = null;
        if ($discountCode !== null && $discountCode !== '') {
            $d = Discount::findOne(['code' => $discountCode]);
            if ($d === null || !$d->appliesTo($price->product, $price->currency)) {
                throw new \InvalidArgumentException('This discount code is not valid.');
            }
        }
        $r = Pricing::apply((int) $price->amount, $d?->percent !== null ? (int) $d->percent : null, $d?->amount_off !== null ? (int) $d->amount_off : null);

        return $r + ['discountModel' => $d];
    }

    /**
     * Create the order and start payment. Redirect the buyer to the returned `redirectUrl`.
     *
     * @throws \InvalidArgumentException for unknown or inactive price, disabled provider or invalid coupon
     */
    public function checkout(string $priceCode, string $providerId, ?int $userId, ?string $discountCode = null, ?string $email = null): CheckoutSession
    {
        $price = Price::findOne(['code' => $priceCode, 'active' => 1]);
        if ($price === null || !$price->product->active) {
            throw new \InvalidArgumentException("Unknown price '$priceCode'.");
        }
        if ($userId === null && !$this->allowGuests) {
            throw new \InvalidArgumentException('Login required.');
        }
        $available = $this->available($price->currency, null, $price->interval ? Capability::SUBSCRIPTION : Capability::ONE_TIME);
        $provider = null;
        foreach ($available as $p) {
            if ($p->id() === $providerId) {
                $provider = $p;
            }
        }
        if ($provider === null) {
            throw new \InvalidArgumentException("Provider '$providerId' is not available for this purchase.");
        }
        $q = $this->quote($price, $discountCode);

        $order = new Order([
            'uuid' => self::uuid(), 'user_id' => $userId, 'product_id' => $price->product_id, 'price_id' => $price->id,
            'discount_id' => $q['discountModel']?->id, 'provider' => $providerId, 'status' => Order::PENDING,
            'currency' => $price->currency, 'amount' => $q['amount'], 'discount_amount' => $q['discount'],
        ]);
        $order->save(false);

        $returnBase = $this->publicUrl !== null
            ? rtrim($this->publicUrl, '/') . '/' . $this->routePrefix . '/return/' . rawurlencode($providerId)
            : Url::to(['/' . $this->routePrefix . '/return', 'provider' => $providerId], true);
        $request = new CheckoutRequest(
            orderUuid: $order->uuid, amount: $q['amount'], currency: $price->currency, name: $price->product->name,
            successUrl: $returnBase . '?order=' . $order->uuid, cancelUrl: $returnBase . '?cancelled=1&order=' . $order->uuid,
            interval: $price->interval, trialDays: (int) $price->trial_days, customerEmail: $email,
            // A coupon changes the amount, so a catalog price id can only be used when no coupon is applied.
            externalPriceId: $q['discount'] === 0 ? $price->externalId($providerId) : null,
            locale: null, metadata: ['product' => $price->product->code],
        );
        $session = $provider->createCheckout($request);
        $order->updateAttributes(['external_id' => $session->externalId, 'updated_at' => time()]);

        return $session;
    }

    /** URL to register at the provider for webhooks. */
    public function webhookUrl(string $providerId): string
    {
        $path = '/' . $this->routePrefix . '/webhook/' . rawurlencode($providerId);

        return $this->publicUrl !== null ? rtrim($this->publicUrl, '/') . $path : Url::to(['/' . $this->routePrefix . '/webhook', 'provider' => $providerId], true);
    }

    public function wallet(): WalletService
    {
        return $this->wallet ??= new WalletService();
    }

    public function processor(): Processor
    {
        return new Processor($this);
    }

    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
