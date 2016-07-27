<?php

namespace ejen\fias\common\models;

use \ejen\fias\Module;

/**
 * Статус актуальности ФИАС
 *
 * @property integer $actstatid Идентификатор статуса (ключ)
 * @property string $actstat Наименование (0 – Не актуальный, 1 – Актуальный (последняя запись по адресному объекту)
 *
 * @package ejen\fias\common\models
 */
class FiasActstat extends \yii\db\ActiveRecord
{
    public static function getDb()
    {
        $module = Module::getInstance();

        return !empty($module) ? $module->getDb() : parent::getDb();
    }

    public static function tableName()
    {
        return '{{%fias_actstat}}';
    }
}
