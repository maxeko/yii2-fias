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
        /*$this->andWhere([
            $alias . "currstatus" => 0
        ]);*/
        $this->andWhere([
            $alias . "copy" => false
        ]);
        return $this;
    }

    public function history()
    {

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
     * Поиск по названию
     * @param string $q строка запроса
     * @param string|null $alias
     * @return $this
     */
    public function byName($q, $alias = null)
    {
        $alias = ($alias ? "{$alias}." : "");
        $this->andWhere([
            'like', "upper({$alias}formalname COLLATE \"ru_RU\")", mb_strtoupper($q)
        ]);
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