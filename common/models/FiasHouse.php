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
 * @property string $fias_houseguid Для строк из "Реестра добавленных адресов ГИС ЖКХ". GUID соответствующей записи ФИАС (если есть)
 * @property string $fias_houseid Для строк из "Реестра добавленных адресов ГИС ЖКХ". ID соответствующей записи ФИАС (если есть)
 *
 * @property FiasAddrobj[] $addrobj
 *
 * @package ejen\fias\common\models
 */
class FiasHouse extends ActiveRecord
{
    /* *
     * Признак владения
     *******************/
    const ESTSTATUS_UNEFINED            = 0; // не определено
    const ESTSTATUS_GROUNDS             = 1; // владение
    const ESTSTATUS_HOUSE               = 2; // дом
    const ESTSTATUS_HOUSE_AND_GROUNDS   = 3; // домовладение

    /* *
     * Признак строения
     *******************/

    const STRSTATUS_UNDEFINED   = 0; // не определено
    const STRSTATUS_SROYENIE    = 1; // строение
    const STRSTATUS_SOORUZHENIE = 2; // сооружение
    const STRSTATUS_LITER       = 3; // литер

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
        return 'fias_house';
    }

    /**
     * Перегрузка конструктора запросов с собственным поисковым классом
     * @return FiasHouseQuery
     */
    public static function find()
    {
        return \Yii::createObject(FiasHouseQuery::className(), [get_called_class()]);
    }

    /**
     * @inheritdoc
     */
    public function extraFields()
    {
        return array_merge(parent::extraFields(), [
            'name' => function (self $model) {
                return $model->getName();
            }
        ]);
    }

    /* *
     * ActiveRecord relations
     *************************/

    /**
     * @return FiasAddrobjQuery
     */
    public function getAddrobj()
    {
        return $this->hasMany(FiasAddrobj::className(), ['aoguid' => 'aoguid']);
    }

    /**
     * @return FiasAddrobj|null
     */
    public function getActualAddrobj()
    {
        return $this->getAddrobj()->actual()->one();
    }

    /* *
     * Public helpers
     ****************/

    /**
     * Получить полную строку "дом, корпус, строение"
     * @return string
     */
    public function getName()
    {
        $eststatusPrefix = '';
        switch ($this->eststatus) {
            case static::ESTSTATUS_GROUNDS:
                $eststatusPrefix = 'владение ';
                break;
            case static::ESTSTATUS_HOUSE:
                $eststatusPrefix = 'дом ';
                break;
            case static::ESTSTATUS_HOUSE_AND_GROUNDS:
                $eststatusPrefix = 'домовладение ';
                break;
        }

        $strstatusPrefix = 'строение';
        switch ($this->strstatus) {
            case static::STRSTATUS_SOORUZHENIE:
                $strstatusPrefix = 'сооружение';
                break;
            case static::STRSTATUS_LITER:
                $strstatusPrefix = 'литер';
                break;
        }
        $parts = [
            $eststatusPrefix . trim($this->housenum), // дом (владение, домовладение)
            $this->buildnum ? "корп. " . trim($this->buildnum) : false, // корпус
            $this->strucnum ? $strstatusPrefix . trim($this->strucnum) : false // строение (сооружение, литер)
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
        return sprintf('%s, %s', $this->getAddrobj()->last()->one()->fulltext_search, $this->getName());
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
        $addrobj = $this->getAddrobj()->last()->one();

        while ($addrobj && $addrobj->aolevel != $aolevel) {
            $addrobj = $addrobj->getParent()->last()->one();
        }

        return $addrobj;
    }
}
