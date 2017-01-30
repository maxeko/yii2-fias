<?php

namespace ejen\fias\web\controllers;

use ejen\fias\common\models\FiasAddrobj;
use ejen\fias\web\models\AddrobjSearchForm;
use startuplab\helpers\ArrayHelper;
use yii\data\ActiveDataProvider;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\ContentNegotiator;

/**
 * Выборка адресообразующих элементов
 * @mixin ContentNegotiator
 */
class AddrobjController extends Controller
{
    public function behaviors()
    {
        return [
            'contentNegotiator' => [
                'class' => ContentNegotiator::className(),
                'formatParam' => '_format',
                'formats' => [
                    'text/html' => Response::FORMAT_HTML,
                    'application/json' => Response::FORMAT_JSON,
                    'application/xml' => Response::FORMAT_XML,
                ],
            ],
        ];
    }

    /**
     * Поиск адресообразующих элементов по полному наименованию
     */
    public function actionSearch()
    {
        $form = new AddrobjSearchForm();

        $form->load(\Yii::$app->request->get());

        if (!$form->validate()) {
            throw new BadRequestHttpException(json_encode($form->errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $dataProvider = new ActiveDataProvider();
        $dataProvider->query = $form->query();

        if (\Yii::$app->request->isAjax) {
            return $dataProvider->getModels();
        }

        //return $dataProvider->query->createCommand()->rawSql;
        return json_encode(ArrayHelper::toArray($dataProvider->getModels(), [
            FiasAddrobj::className() => [
                'aoguid',
                'formalname',
                'shortname',
                'fulltext_search'
            ]
        ]), JSON_UNESCAPED_UNICODE);
    }
}