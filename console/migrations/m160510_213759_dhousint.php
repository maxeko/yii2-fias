<?php

require_once('m160510_195752_houseint.php');

class m160510_213759_dhousint extends m160510_195752_houseint
{
    public $tableName = '{{%fias_dhousint}}';

    public function init()
    {
        $module = ejen\fias\Module::getInstance();

        if (!empty($module)) {
            $this->db = $module->getDb();
        }

        parent::init();
    }
}
