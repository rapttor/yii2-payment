<?php
/**
 * @var yii\web\View $this
 * @var string $title
 * @var yii\db\ActiveRecord $model
 * @var array<string,string> $fields
 */

use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = $title;
?>
<div class="payments-admin">
    <?= $this->render('@rapttor/payment/backoffice/views/_nav') ?>
    <h1><?= Html::encode($title) ?></h1>
    <?php $form = ActiveForm::begin(); ?>
    <?php foreach ($fields as $attr => $type) : ?>
        <?php
        if ($type === 'checkbox') {
            echo $form->field($model, $attr)->checkbox();
        } elseif ($type === 'textarea') {
            echo $form->field($model, $attr)->textarea(['rows' => 3]);
        } elseif (str_starts_with($type, 'select:')) {
            $opts = explode('|', substr($type, 7));
            echo $form->field($model, $attr)->dropDownList(array_combine($opts, $opts));
        } else {
            echo $form->field($model, $attr)->input($type === 'number' ? 'number' : 'text');
        }
        ?>
    <?php endforeach; ?>
    <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-link']) ?>
    <?php ActiveForm::end(); ?>
</div>
