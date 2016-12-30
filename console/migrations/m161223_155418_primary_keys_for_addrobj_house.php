<?php

use ejen\fias\common\models\FiasHouse;
use yii\db\Migration;
use ejen\fias\common\models\FiasAddrobj;

class m161223_155418_primary_keys_for_addrobj_house extends Migration
{
    public function init()
    {
        $module = ejen\fias\Module::getInstance();

        if (!empty($module)) {
            $this->db = $module->getDb();
        }

        parent::init();
    }

    public function up()
    {
        $this->addPrimaryKey("fias_addrobj_aoid_pk", FiasAddrobj::tableName(), ['aoid']);
        $this->addPrimaryKey("fias_house_houseid_pk", FiasHouse::tableName(), ['houseid']);
    }

    public function down()
    {
        $this->dropPrimaryKey("fias_addrobj_aoid_pk", FiasAddrobj::tableName());
        $this->dropPrimaryKey("fias_house_houseid_pk", FiasHouse::tableName());
    }

    /*
    // Use safeUp/safeDown to run migration code within a transaction
    public function safeUp()
    {
    }

    public function safeDown()
    {
    }
    */
}
