<?php

namespace ejen\fias\web\models;

use ejen\fias\common\models\FiasAddrobj;
use ejen\fias\common\models\FiasHouse;
use ejen\fias\common\models\FiasHouseQuery;
use yii\base\Model;

/**
 * Форма поиска объектов адресации
 */
class HousesSearchForm extends Model
{
    /**
     * @var string $aoquid GUID родительского адресообразующего элемента
     */
    public $aoguid = null;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['aoguid', 'required'],
            ['aoguid', 'exist', 'targetClass' => FiasAddrobj::className(), 'targetAttribute' => 'aoguid'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function formName()
    {
        return '';
    }

    /**
     * Построить запрос на выборку объектов адресации
     * @return FiasHouseQuery
     */
    public function query()
    {
        return FiasHouse::find()
            ->actual()
            ->byAoguid($this->aoguid)
            ->orderBy([
                "CASE housenum WHEN '' THEN 0 ELSE (substring(housenum, '^[0-9]+'))::int END" => SORT_ASC,
                'buildnum' => SORT_ASC,
                'strucnum' => SORT_ASC,
            ]);
    }
}