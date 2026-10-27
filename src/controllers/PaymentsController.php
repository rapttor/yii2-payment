<?php

declare(strict_types=1);

namespace rapttor\payment\controllers;

use rapttor\payment\dto\WebhookEvent;
use rapttor\payment\models\Order;
use rapttor\payment\Payments;
use rapttor\payment\providers\InvalidSignatureException;
use rapttor\payment\providers\ReturnHandlerInterface;
use Yii;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/** Public endpoints: start checkout, receive webhooks, handle the buyer's return, fake sandbox page. */
final class PaymentsController extends Controller
{
    public function behaviors(): array
    {
        return ['verbs' => ['class' => VerbFilter::class, 'actions' => ['checkout' => ['post'], 'webhook' => ['post']]]];
    }

    public function beforeAction($action): bool
    {
        // Providers cannot send our CSRF token.
        $this->enableCsrfValidation = !in_array($action->id, ['webhook'], true);

        return parent::beforeAction($action);
    }

    private function payments(): Payments
    {
        return Yii::$app->get('payments');
    }

    public function actionCheckout(): Response
    {
        $r = Yii::$app->request;
        $payments = $this->payments();
        if (Yii::$app->user->isGuest && !$payments->allowGuests) {
            return Yii::$app->user->loginRequired();
        }
        try {
            $session = $payments->checkout(
                (string) $r->post('price'),
                (string) $r->post('provider'),
                Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id,
                ($d = trim((string) $r->post('discount'))) !== '' ? $d : null,
                Yii::$app->user->isGuest ? null : (Yii::$app->user->identity->email ?? null),
            );
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());

            return $this->redirect(Yii::$app->request->referrer ?: $payments->returnUrl);
        }

        return $this->redirect($session->redirectUrl);
    }

    public function actionWebhook(string $provider): Response
    {
        $payments = $this->payments();
        if (!isset($payments->providers[$provider])) {
            throw new NotFoundHttpException();
        }
        $headers = [];
        foreach (Yii::$app->request->headers as $name => $values) {
            $headers[strtolower($name)] = is_array($values) ? (string) reset($values) : (string) $values;
        }
        try {
            $events = $payments->provider($provider)->parseWebhook(Yii::$app->request->getRawBody(), $headers);
        } catch (InvalidSignatureException $e) {
            Yii::warning($e->getMessage(), 'payments');
            throw new BadRequestHttpException('Invalid signature');
        }
        foreach ($events as $event) {
            $payments->processor()->handle($event);
        }
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->content = 'ok';

        return Yii::$app->response;
    }

    /** Buyer comes back from the provider (success or cancel). Capture if the provider needs it, then go to the app. */
    public function actionReturn(string $provider): Response
    {
        $payments = $this->payments();
        if (!isset($payments->providers[$provider])) {
            throw new NotFoundHttpException();
        }
        $q = Yii::$app->request->get();
        $status = empty($q['cancelled']) ? 'processing' : 'cancelled';
        $p = $payments->provider($provider);
        if ($status === 'processing' && $p instanceof ReturnHandlerInterface) {
            foreach ($p->handleReturn($q) as $event) {
                $payments->processor()->handle($event);
            }
        }
        $uuid = (string) ($q['order'] ?? '');
        $order = $uuid !== '' ? Order::findOne(['uuid' => $uuid, 'provider' => $provider]) : null;
        if ($order !== null && $status !== 'cancelled') {
            $status = $order->status === Order::PAID ? 'paid' : 'processing';
        }
        $to = (array) $payments->returnUrl;
        $to[0] = $to[0] ?? '/';

        return $this->redirect($to + ['order' => $uuid, 'status' => $status]);
    }

    /** Fake provider: lets you click through a payment in development without any keys. */
    public function actionSandboxPay(): string|Response
    {
        $payments = $this->payments();
        if (!$payments->isEnabled('sandbox')) {
            throw new NotFoundHttpException();
        }
        $uuid = (string) Yii::$app->request->get('order', Yii::$app->request->post('order'));
        $order = Order::findOne(['uuid' => $uuid, 'provider' => 'sandbox']);
        if ($order === null) {
            throw new NotFoundHttpException();
        }
        if (Yii::$app->request->isPost) {
            $ok = Yii::$app->request->post('result') === 'pay';
            $payments->processor()->handle(new WebhookEvent('sandbox', 'sandbox:' . $uuid . ':' . ($ok ? 'paid' : 'failed'), $ok ? WebhookEvent::PAID : WebhookEvent::FAILED, $uuid, 'sandbox-pay-' . $uuid, null, (int) $order->amount, $order->currency));
            $to = (array) $payments->returnUrl;
            $to[0] = $to[0] ?? '/';

            return $this->redirect($to + ['order' => $uuid, 'status' => $ok ? 'paid' : 'cancelled']);
        }

        return $this->renderFile(dirname(__DIR__) . '/views/sandbox/pay.php', ['order' => $order]);
    }
}
