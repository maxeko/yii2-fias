<?php

use yii\db\Migration;

class m161221_104323_house_indexes extends Migration
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
        $this->execute("CREATE INDEX \"fias_house_houseguid_enddate\" ON \"fias_house\" (\"houseguid\", \"enddate\")");
        $this->execute("CREATE INDEX \"fias_house_housenum_aoguid_enddate\" ON \"fias_house\" (\"housenum\", \"aoguid\", \"enddate\")");
        $this->execute("CREATE INDEX \"fias_house_aoguid_enddate\" ON \"fias_house\" (\"aoguid\", \"enddate\")");
    }

    public function down()
    {
        $this->execute("DROP INDEX \"fias_house_houseguid_enddate\"");
        $this->execute("DROP INDEX \"fias_house_housenum_aoguid_enddate\"");
        $this->execute("DROP INDEX \"fias_house_aoguid_enddate\"");
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
