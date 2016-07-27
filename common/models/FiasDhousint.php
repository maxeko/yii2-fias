<?php

namespace ejen\fias\common\models;

class FiasDhousint extends FiasHouseint
{
    public static function getDb()
    {
        return \ejen\fias\Module::getInstance()->getDb();
    }

    public static function tableName()
    {
        return '{{%fias_dhousint}}';
    }
}
