<?php

namespace ejen\fias\common\models;

use ejen\fias\common\helpers\FiasHelper;
use \yii\db\ActiveRecord;
use \ejen\fias\Module;

/**
 * Объект адресации
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
 * @property string $buildnum Корпус
 * @property string $strucnum Номер строения
 * @property integer $eststatus Признак владения
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
 * @property boolean $gisgkh запись из "дельты" ГИС ЖКХ
 * @property boolean $copy запись является копией, не учитывать в выбораках
 *
 * @property FiasAddrobj $addrobj
 *
 * @package ejen\fias\common\models
 */
class FiasHouse extends ActiveRecord
{
    /* *
     * ActiveRecord
     ***************/

    /**
     * @inheritdoc
     */
    public static function getDb()
    {
        $module = Module::getInstance();

        return !empty($module) ? $module->getDb() : parent::getDb();
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%fias_house}}';
    }

    /**
     * Перегрузка конструктора запросов с собственным поисковым классом
     * @return FiasHouseQuery
     */
    public static function find()
    {
        return \Yii::createObject(FiasHouseQuery::className(), [get_called_class()]);
    }

    /* *
     * ActiveRecord relations
     *************************/

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAddrobj()
    {
        return $this->hasOne(FiasAddrobj::className(), ['aoguid' => 'aoguid']);
    }

    /* *
     * Public helpers
     ****************/

    /**
     * Получить полную строку с литерами (дом, корп. .., стр. ..)
     * @return string
     */
    public function getName()
    {
        $parts = [
            $this->housenum,
            $this->buildnum ? "корп. {$this->buildnum}" : false,
            $this->strucnum ? "стр. {$this->strucnum}" : false
        ];
        $parts = array_filter($parts);
        return join(', ', $parts);
    }

    /**
     * Полный адрес объекта
     * @todo: сдлеать кастомное фотматирование типа %R, %C, %S, %h
     * @return string
     */
    public function toString()
    {
        return sprintf('%s, %s', FiasHelper::toFullString($this->addrobj->aoguid), $this->getName());
    }

    /**
     * Получить улицу (адресообразующий элемент)
     * @return FiasAddrobj|null
     */
    public function getStreet()
    {
        return $this->getAddrobjByLevel(FiasAddrobj::AOLEVEL_STREET);
    }

    /**
     * Получить город (адресообразующий элемент)
     * @return FiasAddrobj|null
     */
    public function getCity()
    {
        return $this->getAddrobjByLevel(FiasAddrobj::AOLEVEL_CITY);
    }

    /**
     * Получить регион (адресообразующий элемент)
     * @return FiasAddrobj|null
     */
    public function getRegion()
    {
        return $this->getAddrobjByLevel(FiasAddrobj::AOLEVEL_REGION);
    }

    /* *
     * Private helpers
     ******************/

    /**
     * Получить адресообразующийэлемент заданного уровня
     * @param integer|null $aolevel
     * @return FiasAddrobj|null
     */
    private function getAddrobjByLevel($aolevel = null)
    {
        $addrobj = $this->addrobj;

        while ($addrobj && $addrobj->aolevel != $aolevel) {
            $addrobj = $addrobj->parent;
        }

        return $addrobj;
    }
}
