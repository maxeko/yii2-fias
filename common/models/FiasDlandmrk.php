<?php

namespace ejen\fias\common\models;

class FiasDlandmrk extends FiasLandmark
{
    public static function getDb()
    {
        return \ejen\fias\Module::getInstance()->getDb();
    }

    public static function tableName()
    {
        return '{{%fias_dlandmrk}}';
    }
}
