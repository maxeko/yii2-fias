<?php

namespace ejen\fias\common\models;

use yii\db\ActiveQuery;

/**
 * Конструктор запросов к таблице адресообразующих элементов
 */
class FiasAddrobjQuery extends ActiveQuery
{
    /**
     * @inheritdoc
     * @return array|FiasAddrobj[]
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return array|null|FiasAddrobj
     */
    public function one($db = null)
    {
        return parent::one($db);
    }

    /**
     * Только актуальные записи (не исторические, и не копии)
     * Проверка
     * - по полю currstatus == 0 (статус актуальности КЛАДР4)
     * - по полю copy (copy == false, не является копией) -- отсечение ФИАС-овских объектов там где есть ГИС-овские
     * @param string|null $alias
     * @return $this
     */
    public function actual($alias = null)
    {
        $alias = ($alias ? "{$alias}." : "");

        // http://wiki.gis-lab.info/w/%D0%A4%D0%98%D0%90%D0%A1#.D0.A1.D1.82.D0.B0.D1.82.D1.83.D1.81_.D0.B0.D0.BA.D1.82.D1.83.D0.B0.D0.BB.D1.8C.D0.BD.D0.BE.D1.81.D1.82.D0.B8
        $this->andWhere([$alias . "currstatus" => 0]);

        return $this;
    }

    /**
     * Получить только последне адреса в исторической цепочке
     * @param string $alias
     * @return $this
     */
    public function last($alias = null)
    {
        $alias = ($alias ? "{$alias}." : "");

        $this->andWhere("({$alias}currstatus = 0 OR {$alias}nextid IS NULL OR {$alias}nextid = '')");

        return $this;
    }

    /**
     * Выбрать только записи не помеченные как "копии" (в приоритете записи добавленные в ГИС)
     * @param string $alias
     * @return $this
     */
    public function validForGisgkh($alias = null)
    {
        $alias = empty($alias) ? '' : $alias . '.';

        $this->andWhere(["{$alias}copy" => false]);

        return $this;
    }

    /**
     * Поиск по GUID-у
     * @param string $aoguid глобально уникальный идентификатор
     * @param string|null $alias
     * @return $this
     */
    public function byGuid($aoguid, $alias = null)
    {
        $alias = ($alias ? "{$alias}." : "");
        $this->andWhere([
            $alias . "aoguid" => $aoguid
        ]);
        return $this;
    }

    /**
     * Поиск по GUID-у родительского элемента
     * @param string $aoguid глобально уникальный идентификатор
     * @param string|null $alias
     * @return $this
     */
    public function byParentGuid($aoguid, $alias = null)
    {
        $alias = ($alias ? "{$alias}." : "");
        $this->andWhere([
            $alias . "parentguid" => $aoguid
        ]);
        return $this;
    }

    /**
     * Поиск по официальному наименованию
     * @param string $q строка запроса
     * @param string|null $alias
     * @return $this
     */
    public function byFormalName($q, $alias = null)
    {
        $alias = ($alias ? "{$alias}." : "");
        $this->andWhere([
            'like', "upper({$alias}formalname COLLATE \"ru_RU\")", mb_strtoupper($q)
        ]);
        return $this;
    }

    /**
     * Поиск по полному названию
     * @param string $q строка запроса
     * @param string|null $alias
     * @return $this
     */
    public function byFullName($q, $alias = null)
    {
        $alias = ($alias ? "{$alias}." : "");

        $parts = mb_split('[\s,.]+', $q);

        foreach ($parts as $part) {
            $this->andWhere([
                'like', "upper({$alias}fulltext_search COLLATE \"ru_RU\")", mb_strtoupper($part)
            ]);
        }

        return $this;
    }

    /**
     * Выбрать адресообразующие элементы заданного уровня
     * @param string $aolevel уровень адресообразующего элемента (см. константы `FiasAddrobj::AOLEVEL_*`)
     * @param string|null $alias
     * @return $this
     */
    public function byLevel($aolevel, $alias = null)
    {
        $alias = ($alias ? "{$alias}." : "");
        $this->andWhere([
            $alias . "aolevel" => $aolevel
        ]);
        return $this;
    }

    /**
     * Ограничить выборку заданным регионом (по коду региона)
     * @param integer $regionCode
     * @param string|null $alias
     * @return $this
     */
    public function byRegionCode($regionCode, $alias = null)
    {
        $alias = ($alias ? "{$alias}." : "");
        $this->andWhere([
            $alias . "regioncode" => $regionCode
        ]);
        return $this;
    }

    /**
     * Выбрать только те элементы у которых есть подчинённые записи об адресных объекта
     * @param string|null $alias
     * @return $this
     */
    public function withChildHouses($alias = null)
    {
        $alias = ($alias ? "{$alias}." : "");
        $this->andWhere([
            '>', $alias . "houses_count", 0
        ]);
        return $this;
    }

    /**
     * Сортировать по названию
     * @param string|null $alias
     * @return $this
     */
    public function orderByName($alias = null)
    {
        $alias = ($alias ? "{$alias}." : "");
        $this->orderBy([$alias . 'formalname' => SORT_ASC]);
        return $this;
    }
}