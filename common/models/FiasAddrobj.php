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

    public static function findAddress(
    $data = [
        'region_id' => null,
        'city_id' => null,
        'street_id' => null,
        'houseguid' => null,
        'offset' => 0,
        'limit' => 50
    ]
    )
    {
        $addresses = [];

        if (!empty($data['houseguid']) || (!empty($data['housenum']) && !empty($data['street_id']))) {
            $addresses = self::getAddressByHouseComplete($data);
            return $addresses;
        }

        if (!empty($data['street'])) {
            $addresses = self::getAddressByStreetComplete($data);
            return $addresses;
        }

        if (!empty($data['city'])) {
            $addresses = self::getAddressByCityComplete($data);
            return $addresses;
        }

        if (!empty($data['region'])) {
            $addresses = self::getAddressByRegionComplete($data);
            return $addresses;
        }
    }

    public static function getAddressByRegionComplete(
    $data = [
        'region_id' => null,
        'offset' => 0,
        'limit' => 50
    ])
    {
        $addresses = [];
        $addresses['items'] = self::getAddressByRegion($data);
        $addresses['count_total'] = self::getCountAddressByRegion($data);
        $addresses['offset'] = $data['offset'];

        return $addresses;
    }

    public static function getCountAddressByRegion(
    $data = [
        'region_id' => null,
        'offset' => 0,
        'limit' => 50,
        'getCount' => true
    ])
    {
        $data['getCount'] = true;
        $data['limit'] = null;

        $addressesCount = self::getAddressByRegion($data);

        return $addressesCount;
    }

    public static function getAddressByRegion(
    $data = [
        'region_id' => null,
        'offset' => 0,
        'limit' => 50,
        'getCount' => false
    ]
    )
    {
        /* @var ActiveQuery $query */
        $query = FiasHouse::find()->
        innerJoinWith(['addrobj AS address' => function($query) {
                $query->andWhere(['address.currstatus' => 0]);
            },
            'addrobj.parent AS parent' => function($query) {
                $query->andWhere(['parent.currstatus' => 0]);
            },
            'addrobj.parent.parent AS parent2' => function($query) use ($data) {
                $query->andWhere(['parent2.currstatus' => 0]);
                $query->andWhere(['parent2.aoguid' => $data['region_id']]);
            }], true)->
        indexBy('houseguid')->
        andWhere(['>', 'fias_house.enddate', 'NOW()'])->
        andWhere(['fias_house.strstatus' => 0]);

        if (!empty($data['getCount'])) {
            $regionsCount = $query->count();

            return $regionsCount;
        }

        if (!empty($data['limit'])) {
            $query = $query->offset($data['offset'])->limit($data['limit']);
        }

        /* @var FiasAddrobj[] $children */
        $query = $query->orderBy([
            'parent2.formalname' => SORT_ASC,
            'parent.formalname' => SORT_ASC,
            'address.formalname' => SORT_ASC,
            "(substring(fias_house.housenum, '^[0-9]+'))::int,substring(fias_house.housenum, '[^0-9_].*$')" => SORT_ASC
        ]);

        $regions = $query->asArray()->all();

        return $regions;
    }

    public static function getAddressBycityComplete(
    $data = [
        'city_id' => null,
        'offset' => 0,
        'limit' => 50
    ])
    {
        $addresses = [];
        $addresses['items'] = self::getAddressByCity($data);
        $addresses['count_total'] = self::getCountAddressByCity($data);
        $addresses['offset'] = $data['offset'];

        return $addresses;
    }

    public static function getCountAddressByCity(
    $data = [
        'city_id' => null,
        'offset' => 0,
        'limit' => 50,
        'getCount' => true
    ])
    {
        $data['getCount'] = true;
        $data['limit'] = null;

        $addressesCount = self::getAddressByCity($data);

        return $addressesCount;
    }

    public static function getAddressByCity(
    $data = [
        'city_id' => null,
        'offset' => 0,
        'limit' => 50,
        'getCount' => false
    ]
    )
    {
        /* @var ActiveQuery $query */
        $query = FiasHouse::find()->
        innerJoinWith(['addrobj AS address' => function($query) {
                $query->andWhere(['address.currstatus' => 0]);
            },
            'addrobj.parent AS parent' => function($query) use ($data) {
                $query->andWhere(['parent.currstatus' => 0]);
                $query->andWhere(['parent.aoguid' => $data['city_id']]);
            },
            'addrobj.parent.parent AS parent2' => function($query) {
                $query->andWhere(['parent2.currstatus' => 0]);
            }], true)->
        indexBy('houseguid')->
        andWhere(['>', 'fias_house.enddate', 'NOW()'])->
        andWhere(['fias_house.strstatus' => 0]);

        if (!empty($data['getCount'])) {
            $citysCount = $query->count();

            return $citysCount;
        }

        if (!empty($data['limit'])) {
            $query = $query->offset($data['offset'])->limit($data['limit']);
        }

        /* @var FiasAddrobj[] $children */
        $query = $query->orderBy([
            'parent2.formalname' => SORT_ASC,
            'parent.formalname' => SORT_ASC,
            'address.formalname' => SORT_ASC,
            "(substring(fias_house.housenum, '^[0-9]+'))::int,substring(fias_house.housenum, '[^0-9_].*$')" => SORT_ASC
        ]);

        $cities = $query->asArray()->all();

        return $cities;
    }

    public static function getAddressByStreetComplete(
    $data = [
        'streetguid' => null,
        'offset' => 0,
        'limit' => 50
    ])
    {
        $addresses = [];
        $addresses['items'] = self::getAddressByStreet($data);
        $addresses['count_total'] = self::getCountAddressByStreet($data);
        $addresses['offset'] = $data['offset'];

        return $addresses;
    }

    public static function getCountAddressByStreet(
    $data = [
        'streetguid' => null,
        'offset' => 0,
        'limit' => 50,
        'getCount' => true
    ])
    {
        $data['getCount'] = true;
        $data['limit'] = null;

        $addressesCount = self::getAddressByStreet($data);

        return $addressesCount;
    }

    public static function getAddressByStreet(
    $data = [
        'street_id' => null,
        'offset' => 0,
        'limit' => 50,
        'getCount' => false
    ]
    )
    {
        /* @var ActiveQuery $query */
        $query = FiasHouse::find()->
        innerJoinWith(['addrobj AS address' => function($query) use ($data) {
                $query->andWhere(['address.currstatus' => 0]);
                $query->andWhere(['address.aoguid' => $data['street_id']]);
            }], true)->
        indexBy('houseguid')->
        andWhere(['>', 'fias_house.enddate', 'NOW()'])->
        andWhere(['fias_house.strstatus' => 0]);

        if (!empty($data['getCount'])) {
            $streetsCount = $query->count();

            return $streetsCount;
        }

        $query = $query->joinWith([
            'addrobj.parent AS parent' => function($query) {
                $query->andWhere(['parent.currstatus' => 0]);
            },
            'addrobj.parent.parent AS parent2' => function($query) {
                $query->andWhere(['parent2.currstatus' => 0]);
            }], true);

        /* @var FiasAddrobj[] $children */
        $query = $query->orderBy([
            'parent2.formalname' => SORT_ASC,
            'parent.formalname' => SORT_ASC,
            'address.formalname' => SORT_ASC,
            "(substring(fias_house.housenum, '^[0-9]+'))::int,substring(fias_house.housenum, '[^0-9_].*$')" => SORT_ASC
        ]);

        if (!empty($data['limit'])) {
            $query = $query->offset($data['offset'])->limit($data['limit']);
        }

        $streets = $query->asArray()->all();

        return $streets;
    }

    public static function getAddressByHouseComplete(
    $data = [
        'houseguid' => null,
        'housenum' => null,
        'street_id' => null,
        'offset' => 0,
        'limit' => 50
    ])
    {
        $addresses = [];
        $addresses['items'] = self::getAddressByHouse($data);
        $addresses['count_total'] = self::getCountAddressByHouse($data);
        $addresses['offset'] = $data['offset'];

        return $addresses;
    }

    public static function getCountAddressByHouse(
    $data = [
        'houseguid' => null,
        'housenum' => null,
        'street_id' => null,
        'offset' => 0,
        'limit' => 50,
        'getCount' => true
    ])
    {
        $data['getCount'] = true;

        $addressesCount = self::getAddressByHouse($data);

        return $addressesCount;
    }

    public static function getAddressByHouse(
    $data = [
        'houseguid' => null,
        'housenum' => null,
        'street_id' => null,
        'offset' => 0,
        'limit' => 50,
        'getCount' => false
    ]
    )
    {
        $where = [];

        if (!empty($data['houseguid'])) {
            $where['fias_house.houseguid'] = $data['houseguid'];
        } else if (!empty($data['housenum'])) {
            $where['fias_house.housenum'] = $data['housenum'];
            $where['address.aoguid'] = $data['street_id'];
        }

        /* @var ActiveQuery $query */
        $query = FiasHouse::find()->
        indexBy('houseguid')->
        joinWith(['addrobj AS address' => function($query) {
                $query->andWhere(['address.currstatus' => 0]);
            },
            'addrobj.parent AS parent' => function($query) {
                $query->andWhere(['parent.currstatus' => 0]);
            },
            'addrobj.parent.parent AS parent2' => function($query) {
                $query->andWhere(['parent2.currstatus' => 0]);
            }])->
        where($where)->
        andWhere(['>', 'fias_house.enddate', 'NOW()'])->
        andWhere(['fias_house.strstatus' => 0]);

        if (!empty($data['getCount'])) {
            $housesCount = $query->count();

            return $housesCount;
        }

        if (!empty($data['limit'])) {
            $query = $query->offset($data['offset'])->limit($data['limit']);
        }

        $query = $query->orderBy([
            'parent2.formalname' => SORT_ASC,
            'parent.formalname' => SORT_ASC,
            'address.formalname' => SORT_ASC,
            "(substring(fias_house.housenum, '^[0-9]+'))::int,substring(fias_house.housenum, '[^0-9_].*$')" => SORT_ASC
        ]);

        /* @var FiasAddrobj[] $children */
        $houses = $query->asArray()->all();

        return $houses;
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
                'like', 'upper(formalname)', mb_convert_case($formalname, MB_CASE_TITLE, "UTF-8")
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
                'like', 'upper(housenum)', mb_convert_case($formalname, MB_CASE_TITLE, "UTF-8")
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
        'getCount' => false,
        'limit' => null,
        'offset' => 0
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
            $addressesQuery = $addressesQuery->andWhere(['like', 'upper(formalname)', mb_convert_case($data['formalname'], MB_CASE_TITLE, "UTF-8")]);
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
