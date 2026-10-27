<?php

declare(strict_types=1);

namespace rapttor\payment\backoffice\controllers;

use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\web\NotFoundHttpException;

/** Generic list/create/update/delete for the catalog tables. Subclasses say which model and which columns. */
abstract class CrudController extends BaseController
{
    /** @return class-string<ActiveRecord> */
    abstract protected function modelClass(): string;

    abstract protected function title(): string;

    /** @return array<int,string|array> GridView columns */
    abstract protected function columns(): array;

    /** @return array<string,string> attribute => 'text'|'number'|'textarea'|'checkbox'|'select:<a|b|c>' form fields */
    abstract protected function fields(): array;

    protected function defaults(): array
    {
        return [];
    }

    public function actionIndex(): string
    {
        $class = $this->modelClass();

        return $this->render('@rapttor/payment/backoffice/views/crud/index', [
            'title' => $this->title(),
            'columns' => $this->columns(),
            'provider' => new ActiveDataProvider(['query' => $class::find()->orderBy(['id' => SORT_DESC]), 'pagination' => ['pageSize' => 25]]),
        ]);
    }

    public function actionCreate(): string|\yii\web\Response
    {
        $class = $this->modelClass();

        return $this->edit(new $class($this->defaults()));
    }

    public function actionUpdate(int $id): string|\yii\web\Response
    {
        return $this->edit($this->find($id));
    }

    public function actionDelete(int $id): \yii\web\Response
    {
        $this->find($id)->delete();

        return $this->redirect(['index']);
    }

    private function edit(ActiveRecord $model): string|\yii\web\Response
    {
        if ($model->load(Yii::$app->request->post())) {
            foreach ($this->fields() as $attr => $type) {
                if (in_array($model->$attr, ['', null], true) && $type !== 'checkbox') {
                    $model->$attr = null;
                }
            }
            if ($model->save()) {
                return $this->redirect(['index']);
            }
        }

        return $this->render('@rapttor/payment/backoffice/views/crud/form', ['title' => $this->title(), 'model' => $model, 'fields' => $this->fields()]);
    }

    private function find(int $id): ActiveRecord
    {
        $class = $this->modelClass();

        return $class::findOne($id) ?? throw new NotFoundHttpException();
    }
}
