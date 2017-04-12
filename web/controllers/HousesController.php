<?php

namespace ejen\fias\web\controllers;

use yii\helpers\ArrayHelper;
use ejen\fias\common\models\FiasHouse;
use ejen\fias\web\models\HousesSearchForm;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\ContentNegotiator;

/**
 * Объекты адресации
 * @mixin ContentNegotiator
 */
class HousesController extends Controller
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
     * Выборка объектов адресации по адресообразующему элементу
     */
    public function actionIndex()
    {
        $form = new HousesSearchForm();

        $form->load(\Yii::$app->request->get());

        if (!$form->validate()) {
            throw new BadRequestHttpException(json_encode($form->errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $fields = [
            'houseguid',
            'housenum',
            'buildnum',
            'strucnum',
            'strstatus'
        ];

        $extraFields = [
            'name'
        ];

        $query = $form->query()->select($fields);

        $responseData = ArrayHelper::toArray($query->all(), [
            FiasHouse::className() => array_merge($fields, $extraFields)
        ]);

        if (\Yii::$app->request->isAjax) {
            return $responseData;
        }

        //return $dataProvider->query->createCommand()->rawSql;
        return json_encode($responseData, JSON_UNESCAPED_UNICODE);
    }
}