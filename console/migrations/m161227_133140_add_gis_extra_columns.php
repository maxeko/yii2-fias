<?php

use ejen\fias\common\models\FiasAddrobj;
use ejen\fias\common\models\FiasHouse;
use yii\db\Migration;

class m161227_133140_add_gis_extra_columns extends Migration
{
    public function up()
    {
        $this->addColumn(FiasAddrobj::tableName(), 'fias_addrobjid', 'VARCHAR(36)');
        $this->addColumn(FiasAddrobj::tableName(), 'fias_addrobjguid', 'VARCHAR(36)');

        $this->addColumn(FiasHouse::tableName(), 'fias_houseid', 'VARCHAR(36)');
        $this->addColumn(FiasHouse::tableName(), 'fias_houseguid', 'VARCHAR(36)');
    }

    public function down()
    {
        $this->dropColumn(FiasAddrobj::tableName(), 'fias_addrobjid');
        $this->dropColumn(FiasAddrobj::tableName(), 'fias_addrobjguid');

        $this->dropColumn(FiasHouse::tableName(), 'fias_houseid');
        $this->dropColumn(FiasHouse::tableName(), 'fias_houseguid');
    }
}
