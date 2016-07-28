<?php

namespace ejen\fias\common\models;

use \yii\db\ActiveRecord;
use \ejen\fias\Module;

/**
 * Реестр образующих элементов
 *
 * @property string $aoguid Глобальный уникальный идентификатор адресного объекта
 * @property string $formalname Формализованное наименование
 * @property string $regioncode Код региона
 * @property string $autocode Код автономии
 * @property string $areacode Код района
 * @property string $citycode Код города
 * @property string $ctarcode Код внутригородского района
 * @property string $placecode Код населенного пункта
 * @property string $plancode Код элемента планировочной структуры
 * @property string $streetcode Код улицы
 * @property string $extrcode Код дополнительного адресообразующего элемента
 * @property string $sextcode Код подчиненного дополнительного адресообразующего элемента
 * @property string $offname Официальное наименование
 * @property string $postalcode Почтовый индекс
 * @property string $ifnsfl Код ИФНС ФЛ
 * @property string $terrifnsf Код территориального участка ИФНС ЮЛ
 * @property string $okato ОКАТО
 * @property string $oktmo ОКТМО
 * @property string $updatedate Дата внесения (обновления) записи
 * @property string $shortname Краткое наименование типа объекта
 * @property integer $aolevel Уровень адресного объекта
 * @property string $parentguid Идентификатор объекта родительского объекта
 * @property string $aoid Уникальный идентификатор записи. Ключевое поле.
 * @property string $previd Идентификатор записи связывания с предыдушей исторической записью
 * @property string $nextid Идентификатор записи связывания с последующей исторической записью
 * @property string $code Код адресного элемента одной строкой с признаком актуальности из классификационного кода
 * @property string $plaincode Код адресного элемента одной строкой без признака актуальности (последних двух цифр)
 * @property integer $actstatus Статус последней исторической записи в жизненном цикле адресного объекта: 0 – Не последняя, 1 - Последняя
 * @property integer $livestatus Статус актуальности адресного объекта ФИАС на текущую дату: 0 – Не актуальный, 1 - Актуальный
 * @property integer $centstatus Статус центра
 * @property integer $operstatus Статус действия над записью – причина появления записи (см. OperationStatuses)
 * @property integer $currstatus Статус актуальности КЛАДР 4 (последние две цифры в коде)
 * @property string $startdate Начало действия записи
 * @property string $enddate Окончание действия записи
 * @property string $normdoc Внешний ключ на нормативный документ
 * @property string $cadnum Кадастровый номер
 * @property integer $divtype Тип деления: 0 – не определено, 1 – муниципальное, 2 – административное
 *
 * @property FiasHouse[] $houses
 * @property FiasAddrobj $parent
 * @property FiasAddrobj[] $children
 *
 * @package ejen\fias\common\models
 */
class FiasAddrobj extends ActiveRecord
{

    public static function getDb()
    {
        $module = Module::getInstance();

        return !empty($module) ? $module->getDb() : parent::getDb();
    }

    public static function tableName()
    {
        return '{{%fias_addrobj}}';
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getHouses()
    {
        return $this->hasMany(FiasHouse::className(), ['aoguid' => 'aoguid']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        return $this->hasOne(static::className(), ['aoguid' => 'parentguid']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChildren()
    {
        return $this->hasMany(static::className(), ['parentguid' => 'aoguid']);
    }

    public function getFullName()
    {
        switch ($this->aolevel) {
            // Регион
            case 1:
                if ($this->shortname == 'обл')
                    return $this->formalname . " область";
                break;
            // Район
            case 3:
                if ($this->shortname == 'р-н')
                    return $this->formalname . " район";
                break;
            case 4:
                if ($this->shortname == 'г')
                    return "город " . $this->formalname;
                break;
            // Населенный пункт
            case 6:
                if ($this->shortname == 'д')
                    return "деревня " . $this->formalname;
                if ($this->shortname == 'с')
                    return "село " . $this->formalname;
                if ($this->shortname == 'рп')
                    return "рабочий поселок " . $this->formalname;
                break;
        }
        return $this->formalname . " " . $this->shortname;
    }

    public function getName()
    {
        return $this->formalname . " " . $this->shortname;
    }

    public static function getRegions($formalname = null)
    {
        $data = ['aolevel' => 1, 'formalname' => $formalname];

        $regions = self::getList($data);

        return $regions;
    }

    public static function showChildren($parentGuid, $formalname = null, $count = 100)
    {
        $parentAddrobj = FiasAddrobj::find()
        ->where(['aoguid' => $parentGuid])
        ->one();

        if (empty($parentAddrobj)) {
            return;
        }

        /* @var ActiveQuery $query */
        $query = $parentAddrobj->getChildren()->
        select("*,
                (fias_addrobj.shortname || ' ' || fias_addrobj.formalname) AS title,
                fias_addrobj.aoguid AS id
        ")->
        where(['currstatus' => 0])->
        orderBy(['formalname' => SORT_ASC])->
        limit($count)->
        distinct();

        if (!empty($formalname)) {
            $query->andWhere([
                'ilike', 'formalname', $formalname
            ]);
        }

        /* @var FiasAddrobj[] $children */
        $children = $query->asArray()->all();

        return $children;
    }

    public static function showHouses($parentGuid, $formalname = null, $count = 100)
    {
        $parentAddrobj = FiasAddrobj::find()
        ->where(['aoguid' => $parentGuid])
        ->one();

        if (empty($parentAddrobj)) {
            return;
        }

        /* @var ActiveQuery $query */
        $query = $parentAddrobj->getHouses()->
        select("*,
                fias_house.housenum AS title,
                fias_house.houseguid AS id
        ")->
        orderBy(['buildnum' => SORT_ASC])->
        andWhere(['>', 'enddate', 'NOW()'])->
        andWhere(['strstatus' => 0])->
        limit($count)->
        distinct();

        if (!empty($formalname)) {
            $query->andWhere([
                'ilike', 'housenum', $formalname
            ]);
        }

        /* @var FiasAddrobj[] $children */
        $houses = $query->asArray()->all();

        return $houses;
    }

    public static function getList(
    $data = [
        'aolevel' => 1,
        'formalname' => null,
        'getCount' => false
    ])
    {
        $where = [];

        if (!empty($data['aolevel'])) {
            $where['aolevel'] = $data['aolevel'];
        }

        $addressesQuery = FiasAddrobj::find()->
        select("*,
                (fias_addrobj.formalname || ' ' || fias_addrobj.shortname) AS title,
                fias_addrobj.aoguid AS id
        ")->
        where($where);

        if (!empty($data['formalname'])) {
            $addressesQuery = $addressesQuery->andWhere(['ilike', 'formalname', $data['formalname']]);
        }

        $addresses = [];

        if (!empty($data['getCount'])) {
            $addresses = $addressesQuery->asArray()->count();
        } else {
            $addresses = $addressesQuery->asArray()->all();
        }

        return $addresses;
    }

}
