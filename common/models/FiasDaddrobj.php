<?php

namespace ejen\fias\common\models;

class FiasDaddrobj extends FiasAddrobj
{
    public static function getDb()
    {
        return \ejen\fias\Module::getInstance()->getDb();
    }

    public static function tableName()
    {
        return '{{%fias_daddrobj}}';
    }
}
