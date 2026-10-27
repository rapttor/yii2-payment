<?php

declare(strict_types=1);

namespace rapttor\payment\backoffice;

use Yii;
use yii\base\Module as BaseModule;

/**
 * Mount in the BACKEND app only:
 *   'modules' => ['payments-admin' => ['class' => rapttor\payment\backoffice\Module::class, 'permission' => 'managePayments']]
 * then open /payments-admin. Without `permission` any logged-in backoffice user gets in, so set it.
 */
final class Module extends BaseModule
{
    public $controllerNamespace = 'rapttor\payment\backoffice\controllers';
    public $defaultRoute = 'providers';
    /** RBAC permission or role checked with Yii::$app->user->can(); null = any logged-in user. */
    public ?string $permission = null;
    /** Name of the `payments` component. */
    public string $component = 'payments';

    public function init(): void
    {
        parent::init();
        Yii::setAlias('@rapttor/payment', dirname(__DIR__));
    }

    public function payments(): \rapttor\payment\Payments
    {
        return Yii::$app->get($this->component);
    }
}
