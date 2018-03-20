<?php

namespace ejen\fias\common\models;

use yii\db\ActiveQuery;

/**
 * Конструктор запросов для FiasHouse
 * @package ejen\fias\common\models
 */
class FiasHouseQuery extends ActiveQuery
{
    /**
     * @inheritdoc
     * @return array|FiasHouse[]
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return array|null|FiasHouse
     */
    public function one($db = null)
    {
        return parent::one($db);
    }

    /**
     * Выбрать актуальные записи
     * @param string|null $alias
     * @return $this
     */
    public function actual($alias = null)
    {
        $alias = $alias ?: FiasHouse::tableName();

        $this->andWhere(["{$alias}.actual" => true]);

        return $this;
    }

    /**
     * Выбрать последнюю запись в хронологической цепочке
     * @param string $alias
     * @return FiasHouse
     */
    public function last($alias = null)
    {
        $alias = empty($alias) ? '' : $alias . '.';

        return $this->orderBy(["{$alias}enddate" => SORT_DESC])->one();
    }

    /**
     * Выбрать только записи не помеченные как "копии" (в приоритете записи добавленные в ГИС)
     * @param string $alias
     * @return $this
     */
    public function validForGisgkh($alias = null)
    {
        $alias = $alias ?: FiasHouse::tableName();

        $this->andWhere([
            "or",
            ["$alias.gisgkh_guid" => null],
            "$alias.gisgkh_guid = $alias.houseguid"
        ]);

        return $this;
    }

    /**
     * Выбрать с заданным ID
     * @param string $guid
     * @param string|null $alias
     * @return $this
     */
    public function byId($guid, $alias = null)
    {
        $alias = empty($alias) ? '' : $alias . '.';

        $this->andWhere(["{$alias}houseid" => strtolower($guid)]);

        return $this;
    }

    /**
     * Выбрать с заданным GUID
     * @param string $guid
     * @param string|null $alias
     * @return $this
     */
    public function byGuid($guid, $alias = null)
    {
        $alias = empty($alias) ? '' : $alias . '.';

        $this->andWhere(["{$alias}houseguid" => strtolower($guid)]);

        return $this;
    }

    /**
     * Выбрать для конкретного адресообразующего элемента
     * @param string $aoguid
     * @param string|null $alias
     * @return $this
     */
    public function byAoguid($aoguid, $alias = null)
    {
        $alias = empty($alias) ? '' : $alias . '.';

        $this->andWhere(["{$alias}aoguid" => strtolower($aoguid)]);

        return $this;
    }

    /**
     * Сортировать по номеру в порядке натуральной сотрировки
     * @param string $alias
     * @return $this
     */
    public function naturalOrder($alias = null)
    {
        $alias = $alias ?: FiasHouse::tableName();

        return $this->orderBy([
            "CASE {$alias}.housenum WHEN '' THEN 0 ELSE (substring({$alias}.housenum, '^[0-9]+'))::int END" => SORT_ASC,
            "{$alias}.buildnum" => SORT_ASC,
            "{$alias}.strucnum" => SORT_ASC,
        ]);
    }

    /**
     * По номеру дома
     * @param string $housenum
     * @param string $alias
     * @return $this
     */
    public function byHousenum($housenum, $alias = null)
    {
        $alias = $alias ?: FiasHouse::tableName();

        return $this->andWhere(["upper($alias.housenum COLLATE \"ru_RU\")" => mb_strtoupper($housenum)]);
    }

    /**
     * По номеру строения
     * @param string $buildnum
     * @param string $alias
     * @return $this
     */
    public function byBuildnum($buildnum, $alias = null)
    {
        $alias = $alias ?: FiasHouse::tableName();

        return $this->andWhere(["upper($alias.buildnum COLLATE \"ru_RU\")" => mb_strtoupper($buildnum)]);
    }

    /**
     * По номеру корпуса
     * @param string $strucnum
     * @param string $aliasN
     * @return $this
     */
    public function byStrucnum($strucnum, $alias = null)
    {
        $alias = $alias ?: FiasHouse::tableName();

        return $this->andWhere(["upper($alias.strucnum COLLATE \"ru_RU\")" => mb_strtoupper($strucnum)]);
    }
}