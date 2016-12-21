<?php

use yii\db\Migration;

class m161221_104342_addrobj_indexes extends Migration
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
        $this->execute("CREATE INDEX \"fias_addrobj_aoguid_enddate_livestatus\" ON \"fias_addrobj\" (\"aoguid\", \"enddate\", \"livestatus\")");
        $this->execute("CREATE INDEX \"fias_addrobj_formalname_aolevel_parentguid_livestatus_enddate\" ON \"fias_addrobj\" (\"formalname\", \"aolevel\", \"parentguid\", \"livestatus\", \"enddate\");");
        $this->execute("CREATE INDEX \"fias_addrobj_currstatus_parentguid_formalname\" ON \"fias_addrobj\" (\"currstatus\", \"parentguid\", \"formalname\");");
        $this->execute("CREATE INDEX \"fias_addrobj_aolevel_formalname\" ON \"fias_addrobj\" (\"aolevel\", \"formalname\");");
    }

    public function down()
    {
        $this->execute("DROP INDEX \"fias_addrobj_aoguid_enddate_livestatus\"");
        $this->execute("DROP INDEX \"fias_addrobj_formalname_aolevel_parentguid_livestatus_enddate\"");
        $this->execute("DROP INDEX \"fias_addrobj_currstatus_parentguid_formalname\"");
        $this->execute("DROP INDEX \"fias_addrobj_aolevel_formalname\"");
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
