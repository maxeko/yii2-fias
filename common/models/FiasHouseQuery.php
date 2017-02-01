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
}