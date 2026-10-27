<?php
/**
 * @var yii\web\View $this
 * @var rapttor\payment\Payments $payments
 * @var array<string,rapttor\payment\models\Provider> $rows
 * @var Closure $webhookUrl
 */

use yii\helpers\Html;

$this->title = 'Payment providers';
?>
<div class="payments-admin">
    <?= $this->render('@rapttor/payment/backoffice/views/_nav') ?>
    <h1>Payment providers</h1>
    <p class="text-muted">Switch providers on or off here. Keys live in the app config / environment, never in this screen.</p>
    <?php foreach ($rows as $id => $row) : ?>
        <?php $p = $payments->provider($id); $ok = $p->isConfigured(); ?>
        <div class="card mb-3">
            <div class="card-body">
                <?= Html::beginForm(['save', 'id' => $id], 'post', ['class' => 'row g-2 align-items-end']) ?>
                <div class="col-12 d-flex justify-content-between">
                    <h5 class="mb-0"><?= Html::encode($p->label()) ?>
                        <span class="badge bg-<?= $ok ? 'success' : 'secondary' ?>"><?= $ok ? 'keys set' : 'no keys' ?></span>
                        <?php foreach ($p->capabilities() as $cap) : ?><span class="badge bg-light text-dark"><?= Html::encode($cap) ?></span><?php endforeach; ?>
                    </h5>
                    <label class="form-check form-switch"><?= Html::checkbox('Provider[enabled]', (bool) $row->enabled, ['class' => 'form-check-input', 'disabled' => !$ok && !$row->enabled]) ?> enabled</label>
                </div>
                <div class="col-md-2"><label class="form-label">Mode</label><?= Html::dropDownList('Provider[mode]', $row->mode, ['test' => 'test', 'live' => 'live'], ['class' => 'form-select']) ?></div>
                <div class="col-md-1"><label class="form-label">Order</label><?= Html::input('number', 'Provider[sort]', $row->sort, ['class' => 'form-control']) ?></div>
                <div class="col-md-3"><label class="form-label">Countries (ISO, empty = all)</label><?= Html::textInput('Provider[countries]', $row->countries, ['class' => 'form-control', 'placeholder' => 'RS,BA,HR']) ?></div>
                <div class="col-md-3"><label class="form-label">Currencies (empty = all)</label><?= Html::textInput('Provider[currencies]', $row->currencies, ['class' => 'form-control', 'placeholder' => 'EUR,USD']) ?></div>
                <div class="col-md-3"><?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?></div>
                <?= Html::endForm() ?>
                <?php if (!$ok && $p->requiredSettings()) : ?>
                    <p class="small mt-2 mb-0">Needs: <?= Html::encode(implode(', ', $p->requiredSettings())) ?></p>
                <?php endif; ?>
                <p class="small mt-2 mb-0">Webhook URL: <code><?= Html::encode($webhookUrl($id)) ?></code></p>
                <?php if ($ok && $p instanceof rapttor\payment\providers\CatalogSyncInterface) : ?>
                    <?= Html::beginForm(['sync', 'id' => $id], 'post', ['class' => 'mt-2']) ?>
                    <?= Html::submitButton('Sync catalog to ' . Html::encode($p->label()), ['class' => 'btn btn-sm btn-outline-secondary']) ?>
                    <span class="small text-muted">creates products and prices there and stores their ids on our prices</span>
                    <?= Html::endForm() ?>
                <?php endif; ?>
                <?php if ($p->apiLinks()) : ?>
                    <p class="small mt-2 mb-0">API docs:
                        <?php foreach ($p->apiLinks() as $label => $url) : ?>
                            <?= Html::a(Html::encode($label) . ' ↗', $url, ['target' => '_blank', 'rel' => 'noopener noreferrer', 'class' => 'me-2']) ?>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>
                <?php if ($p->dashboardLinks()) : ?>
                    <p class="small mt-2 mb-0">Open in <?= Html::encode($p->label()) ?>:
                        <?php foreach ($p->dashboardLinks() as $label => $url) : ?>
                            <?= Html::a(Html::encode($label) . ' ↗', $url, ['target' => '_blank', 'rel' => 'noopener noreferrer', 'class' => 'me-2']) ?>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
