<?php

use yii\db\Migration;

class m160730_174140_add_gisgkh_field extends Migration
{

    public $gisGkhField = 'gisgkh';
    public $module;

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
        $this->addColumn($this->module->fiasAddrobjTable, $this->gisGkhField, $this->boolean()->null()->defaultValue(false));
        
        $this->addColumn($this->module->fiasHouseTable, $this->gisGkhField, $this->boolean()->null()->defaultValue(false));
    }

    public function down()
    {
        $this->dropColumn($this->module->fiasAddrobjTable, $this->gisGkhField);
        
        $this->dropColumn($this->module->fiasHouseTable, $this->gisGkhField);
    }
}
