<?php

namespace ejen\fias\common\models;

class FiasCurentst extends \yii\db\ActiveRecord
{
    public static function getDb()
    {
        return \ejen\fias\Module::getInstance()->getDb();
    }

    public static function tableName()
    {
        return '{{%fias_curentst}}';
    }
}
