<?php
/**
 * @var yii\web\View $this
 * @var string $title
 * @var array $columns
 * @var yii\data\ActiveDataProvider $provider
 */

use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\helpers\Html;

$this->title = $title;
$c = Yii::$app->controller->id;
$editable = Yii::$app->controller instanceof rapttor\payment\backoffice\controllers\CrudController;
if ($editable) {
    $columns[] = ['class' => ActionColumn::class, 'template' => '{update} {delete}', 'buttonOptions' => ['data-method' => 'post']];
}
?>
<div class="payments-admin">
    <?= $this->render('@rapttor/payment/backoffice/views/_nav') ?>
    <h1><?= Html::encode($title) ?></h1>
    <?php if ($editable) : ?><p><?= Html::a('Add', ['create'], ['class' => 'btn btn-primary']) ?></p><?php endif; ?>
    <?= GridView::widget(['dataProvider' => $provider, 'columns' => $columns, 'tableOptions' => ['class' => 'table table-striped']]) ?>
</div>
