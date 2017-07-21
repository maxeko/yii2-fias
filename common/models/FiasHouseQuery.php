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
        $alias = empty($alias) ? '' : $alias . '.';

        $this->andWhere(['>', "{$alias}enddate", 'NOW()']);

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
        $alias = empty($alias) ? '' : $alias . '.';

        $this->andWhere(["{$alias}copy" => false]);

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
     * По коду региона
     * @param integer|string $code код региона
     * @param string $addrobjAlias alias к таблице addrobj, если не указан то будет сделан inner join
     * @return $this
     */
    public function byRegionCode($code, $addrobjAlias = null)
    {
        if (empty($addrobjAlias)) {
            $addrobjAlias = FiasAddrobj::tableName();
            $this->joinWith("addrobj {$addrobjAlias}", false, 'INNER JOIN');
        }

        return $this->andWhere([
            "{$addrobjAlias}.regioncode" => (string) $code
        ]);
    }
}