<?php

use ejen\fias\common\models\FiasAddrobj;
use ejen\fias\common\models\FiasHouse;
use yii\db\Migration;

class m161227_102233_add_copy_column extends Migration
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
        $this->alterColumn(FiasAddrobj::tableName(), 'copy', 'BOOLEAN');
        $this->createIndex(FiasAddrobj::tableName() . '_copy', FiasAddrobj::tableName(), ['copy']);

        $this->alterColumn(FiasHouse::tableName(), 'copy', 'BOOLEAN');
        $this->createIndex(FiasHouse::tableName() . '_copy', FiasHouse::tableName(), ['copy']);
    }

    public function down()
    {
        $this->dropColumn(FiasAddrobj::tableName(), 'copy');
        $this->dropColumn(FiasHouse::tableName(), 'copy');
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
