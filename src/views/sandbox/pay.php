<?php
/** @var rapttor\payment\models\Order $order */

use rapttor\payment\Pricing;
use yii\helpers\Html;

?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sandbox payment</title>
<style>body{font:16px/1.5 system-ui,sans-serif;max-width:28rem;margin:4rem auto;padding:0 1rem}button{font:inherit;padding:.6rem 1.2rem;margin-right:.5rem;cursor:pointer}</style></head>
<body>
<h1>Sandbox payment</h1>
<p>Fake checkout, no money moves. <strong><?= Html::encode($order->product->name) ?></strong>: <?= Html::encode(Pricing::format((int) $order->amount, $order->currency)) ?></p>
<form method="post">
    <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
    <?= Html::hiddenInput('order', $order->uuid) ?>
    <button name="result" value="pay">Pay</button>
    <button name="result" value="fail">Fail</button>
</form>
</body></html>
