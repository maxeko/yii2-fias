<?php

require_once('m160510_195814_landmark.php');

class m160510_213811_dlandmrk extends m160510_195814_landmark
{
    public $tableName = '{{%fias_dlandmrk}}';

    public function init()
    {
        $module = ejen\fias\Module::getInstance();

        if (!empty($module)) {
            $this->db = $module->getDb();
        }

        parent::init();
    }
}
