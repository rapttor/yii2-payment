<?php

use yii\helpers\Html;

$flash = Yii::$app->session->getAllFlashes();
$items = ['providers' => 'Providers', 'products' => 'Products', 'prices' => 'Prices', 'discounts' => 'Discounts', 'orders' => 'Orders', 'orders/events' => 'Webhook log'];
?>
<?php foreach ($flash as $type => $msg) : ?>
    <div class="alert alert-<?= $type === 'error' ? 'danger' : 'success' ?>"><?= Html::encode(is_array($msg) ? implode(' ', $msg) : $msg) ?></div>
<?php endforeach; ?>
<ul class="nav nav-pills mb-3">
    <?php foreach ($items as $route => $label) : ?>
        <li class="nav-item"><?= Html::a(Html::encode($label), ['/' . Yii::$app->controller->module->id . '/' . $route], ['class' => 'nav-link' . (str_starts_with(Yii::$app->controller->route, Yii::$app->controller->module->id . '/' . $route) && ($route !== 'orders' || Yii::$app->controller->action->id !== 'events') ? ' active' : '')]) ?></li>
    <?php endforeach; ?>
</ul>
