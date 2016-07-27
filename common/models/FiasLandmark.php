<?php

namespace ejen\fias\common\models;

use ejen\fias\Module;

class FiasLandmark extends \yii\db\ActiveRecord
{
    public static function getDb()
    {
        $module = Module::getInstance();

        return !empty($module) ? $module->getDb() : parent::getDb();
    }

    public static function tableName()
    {
        return '{{%fias_landmark}}';
    }
}
