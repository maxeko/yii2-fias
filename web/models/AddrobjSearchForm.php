<?php

namespace ejen\fias\web\models;

use ejen\fias\common\models\FiasAddrobj;
use ejen\fias\common\models\FiasAddrobjQuery;
use yii\base\Model;

/**
 * Форма поиска адресообразующего элемента
 */
class AddrobjSearchForm extends Model
{
    /**
     * @var string $q строка поискового запроса
     */
    public $q = null;
    /**
     * @var string $regionCode код региона (если поиск ведется в рамках конкретного региона)
     */
    public $regionCode = null;

    /**
     * @inheritdoc
     */
    public function formName()
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['q', 'regionCode'], 'required'],
            ['q', 'string', 'min' => 3],
            ['regionCode', 'number', 'min' => 1, 'max' => 99]
        ];
    }

    /**
     * Построить запрос на выборку адресообразующих элементов на основании формы
     * @return FiasAddrobjQuery
     */
    public function query()
    {
        $query = FiasAddrobj::find()->validForGisgkh()->actual()->withChildHouses();

        if ($this->regionCode) {
            $query->byRegionCode($this->regionCode);
        }

        $query->byFullName($this->q);
        return $query;
    }
}