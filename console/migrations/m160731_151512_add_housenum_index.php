<?php

use yii\db\Migration;

class m160731_151512_add_housenum_index extends Migration
{
    public $module;
    public $indexName = 'housenum_index';

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
        $this->createIndex($this->indexName, $this->module->fiasHouseTable, ['housenum']);
    }

    public function down()
    {
        $this->dropIndex($this->indexName, $this->module->fiasHouseTable);
    }
}
