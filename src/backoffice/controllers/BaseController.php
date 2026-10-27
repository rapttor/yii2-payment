<?php

declare(strict_types=1);

namespace rapttor\payment\backoffice\controllers;

use rapttor\payment\backoffice\Module;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;

abstract class BaseController extends Controller
{
    public function behaviors(): array
    {
        /** @var Module $m */
        $m = $this->module;

        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [['allow' => true, 'roles' => $m->permission ? [$m->permission] : ['@']]],
            ],
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['delete' => ['post'], 'save' => ['post'], 'sync' => ['post'], 'refund' => ['post']]],
        ];
    }

    protected function payments(): \rapttor\payment\Payments
    {
        /** @var Module $m */
        $m = $this->module;

        return $m->payments();
    }
}
