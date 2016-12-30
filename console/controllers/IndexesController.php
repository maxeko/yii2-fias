<?php

namespace ejen\fias\console\controllers;

use ejen\fias\common\models\FiasAddrobj;
use ejen\fias\common\models\FiasHouse;
use ejen\fias\Module;
use yii\base\Exception;
use yii\console\Controller;
use yii\db\Migration;
use yii\db\pgsql\Schema;
use yii\db\Query;

/**
 * Работа с индексами из консоли
 */
class IndexesController extends Controller
{
    public function actionDropAll()
    {
        $migration = new Migration();
        $migration->db = Module::getInstance()->getDb();

        $tableNames = [
            str_replace(['{', '}','%'], '', FiasHouse::tableName()),
            str_replace(['{', '}','%'], '', FiasAddrobj::tableName())
        ];

        foreach ($tableNames as $tableName) {
            $command = Module::getInstance()->getDb()->createCommand("
              SELECT ci.relname as name FROM 
                pg_index i,pg_class ci,pg_class ct 
              WHERE 
                i.indexrelid=ci.oid AND 
                i.indrelid=ct.oid AND
                ct.relname='{$tableName}'
            ");
            $indexes = $command->queryAll();

            foreach ($indexes as $index) {
                try {
                    $migration->dropPrimaryKey($index['name'], $tableName);
                } catch (Exception $e) {
                    $migration->dropIndex($index['name'], $tableName);
                }

            }
        }
    }

    public function actionBuildAll()
    {
        $migration = new Migration();
        $migration->db = Module::getInstance()->getDb();

        $this->addPrimaryKey("fias_addrobj_aoid_pk", FiasAddrobj::tableName(), ['aoid']);
        $this->addPrimaryKey("fias_house_houseid_pk", FiasHouse::tableName(), ['houseid']);

        $this->execute("CREATE INDEX \"fias_house_houseguid_enddate\" ON \"fias_house\" (\"houseguid\", \"enddate\", \"copy\")");
        $this->execute("CREATE INDEX \"fias_house_housenum_aoguid_enddate\" ON \"fias_house\" (\"housenum\", \"aoguid\", \"enddate\", \"copy\")");
        $this->execute("CREATE INDEX \"fias_house_aoguid_enddate\" ON \"fias_house\" (\"aoguid\", \"enddate\", \"copy\")");

        $this->execute("CREATE INDEX \"fias_addrobj_aoguid_enddate_livestatus\" ON \"fias_addrobj\" (\"aoguid\", \"enddate\", \"livestatus\", \"copy\")");
        $this->execute("CREATE INDEX \"fias_addrobj_formalname_aolevel_parentguid_livestatus_enddate\" ON \"fias_addrobj\" (\"formalname\", \"aolevel\", \"parentguid\", \"livestatus\", \"enddate\", \"copy\");");
        $this->execute("CREATE INDEX \"fias_addrobj_currstatus_parentguid_formalname\" ON \"fias_addrobj\" (\"currstatus\", \"parentguid\", \"formalname\", \"copy\");");
        $this->execute("CREATE INDEX \"fias_addrobj_aolevel_formalname\" ON \"fias_addrobj\" (\"aolevel\", \"formalname\", \"copy\");");
    }
}