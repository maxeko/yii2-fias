<?php

namespace ejen\fias\common\models;

use \yii\db\ActiveRecord;
use \ejen\fias\Module;

/**
 * Сведения по отдельным зданиям, сооружениям
 *
 * @property string $postalcode Почтовый индекс
 * @property string $regioncode Код региона
 * @property string $ifnsfl Код ИФНС ФЛ
 * @property string $terrifnsul Код территориального участка ИФНС ФЛ
 * @property string $ifnsul Код ИФНС ЮЛ
 * @property string $okato ОКАТО
 * @property string $oktmo ОКTMO
 * @property string $updatedate Дата время внесения (обновления) записи
 * @property string $housenum Номер дома
 * @property integer $eststatus Признак владения
 * @property string $strucnum Номер строения
 * @property integer $strstatus Признак строения
 * @property string $houseid Уникальный идентификатор записи дома
 * @property string $houseguid Глобальный уникальный идентификатор дома
 * @property string $aoguid Guid записи родительского объекта (улицы, города, населенного пункта и т.п.)
 * @property string $startdate Начало действия записи
 * @property string $enddate Окончание действия записи
 * @property integer $statstatus Состояние дома
 * @property string $normdoc Внешний ключ на нормативный документ
 * @property integer $counter Счетчик записей домов для КЛАДР 4
 *
 * @property FiasAddrobj $addrobj
 *
 * @package ejen\fias\common\models
 */
class FiasHouse extends ActiveRecord
{
    public static function getDb()
    {
        $module = Module::getInstance();

        return !empty($module) ? $module->getDb() : parent::getDb();
    }

    public static function tableName()
    {
        return '{{%fias_house}}';
    }

    public function getAddrobj()
    {
        return $this->hasOne(FiasAddrobj::className(), ['aoguid' => 'aoguid']);
    }
}
