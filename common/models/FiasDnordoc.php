<?php

namespace ejen\fias\common\models;

class FiasDnordoc extends FiasNordoc
{
    public static function getDb()
    {
        return \ejen\fias\Module::getInstance()->getDb();
    }

    public static function tableName()
    {
        return '{{%fias_dnordoc}}';
    }
}
