<?php

require_once('m160510_195826_nordoc.php');

class m160510_213822_dnordoc extends m160510_195826_nordoc
{
    public $tableName = '{{%fias_dnordoc}}';

    public function init()
    {
        $module = ejen\fias\Module::getInstance();

        if (!empty($module)) {
            $this->db = $module->getDb();
        }

        parent::init();
    }
}
