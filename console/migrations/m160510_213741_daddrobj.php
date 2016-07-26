<?php

require_once('m160510_195627_addrobj.php');

class m160510_213741_daddrobj extends m160510_195627_addrobj
{
    public $tableName = '{{%fias_daddrobj}}';

    public function init()
    {
        $module = ejen\fias\Module::getInstance();

        if (!empty($module)) {
            $this->db = $module->getDb();
        }

        parent::init();
    }
}
