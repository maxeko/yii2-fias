<?php

use yii\db\Migration;

class m160731_121610_add_houseguid_index extends Migration
{
    public $module;
    public $indexName = 'houseguid_index';

    public function init()
    {
        $this->module = ejen\fias\Module::getInstance();

        if (!empty($this->module)) {
            $this->db = $this->module->getDb();
        }

        parent::init();
    }
    
    public function up()
    {
        $this->createIndex($this->indexName, $this->module->fiasHouseTable, ['houseguid']);
    }

    public function down()
    {
        $this->dropIndex($this->indexName, $this->module->fiasHouseTable);
    }
}
