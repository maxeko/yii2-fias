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
 * Адресообразующие элементы
 * @mixin ContentNegotiator
 */
class AddrobjController extends Controller
{
    /**
     * @inheritdoc
     */
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
     * Поиск адресообразующих элементов по
     * - региону
     * - строке запроса
     */
    public function actionIndex()
    {
        $form = new AddrobjSearchForm();

        $form->load(\Yii::$app->request->get());

        if (!$form->validate()) {
            throw new BadRequestHttpException(json_encode($form->errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $selectFields = [
            'aoguid',
            'formalname',
            'shortname',
            'fulltext_search'
        ];

        $dataProvider = new ActiveDataProvider();
        $dataProvider->query = $form->query()->select($selectFields);

        if (\Yii::$app->request->isAjax) {
            return $dataProvider->getModels();
        }

        //@todo: сделать дефолтную вьюшку для просмотра адресов
        return json_encode(ArrayHelper::toArray($dataProvider->getModels(), [
            FiasAddrobj::className() => $selectFields
        ]), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Получить полный перечень регионов
     */
    public function actionRegions()
    {
        $fields = [
            'regioncode',
            'formalname',
            'shortname',
        ];

        $extraFields = [
            'name'
        ];

        $query = FiasAddrobj::find()
            ->actual()
            ->byLevel(FiasAddrobj::AOLEVEL_REGION)
            ->orderBy(['regioncode' => SORT_ASC]);

        $dataProvider = new ActiveDataProvider();
        $dataProvider->query = $query;
        $dataProvider->pagination->pageSize = 200;

        if (\Yii::$app->request->isAjax) {
            return ArrayHelper::toArray($dataProvider->getModels(), [
                FiasAddrobj::className() => array_merge($fields, $extraFields)
            ]);
        }

        //@todo: сделать дефолтную вьюшку для просмотра регионов
        return json_encode(ArrayHelper::toArray($dataProvider->getModels(), [
            FiasAddrobj::className() => array_merge($fields, $extraFields)
        ]), JSON_UNESCAPED_UNICODE);
    }
}