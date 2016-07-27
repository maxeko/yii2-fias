<?php

namespace ejen\fias\common\models;

class FiasEststat extends \yii\db\ActiveRecord
{
    public static function getDb()
    {
        return \ejen\fias\Module::getInstance()->getDb();
    }

    public static function tableName()
    {
        return '{{%fias_eststat}}';
    }
}
