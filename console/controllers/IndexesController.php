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

    /**
     * Построитьт все индексы
     */
    public function actionBuildAll()
    {
        $migration = new Migration();
        $migration->db = Module::getInstance()->getDb();

        $migration->addPrimaryKey("fias_house_houseid_pk", FiasHouse::tableName(), ['houseid']);
        $migration->execute("CREATE INDEX \"fias_house_houseguid_enddate\" ON \"fias_house\" (\"houseguid\", \"enddate\", \"copy\")");
        $migration->execute("CREATE INDEX \"fias_house_housenum_aoguid_enddate\" ON \"fias_house\" (\"housenum\", \"aoguid\", \"enddate\", \"copy\")");
        $migration->execute("CREATE INDEX \"fias_house_aoguid_enddate\" ON \"fias_house\" (\"aoguid\", \"enddate\", \"copy\")");

        // PK для адресообразующих элементов
        $migration->addPrimaryKey("fias_addrobj_aoid_pk", FiasAddrobj::tableName(), ['aoid']);

        // быстрый поиск актуального адресообразующего элемента по GUID
        $migration->createIndex('fias_aoguid_ix', FiasAddrobj::tableName(), [
            'aoguid',
            'copy'
        ]);

        // быстрый поиск актуальных адресообразующих элементов по названию и уровню
        $migration->createIndex('fias_aolevel_ix', FiasAddrobj::tableName(), [
            'aolevel',
            'formalname',
            'copy'
        ]);

        // быстрый поиск актуальных адресообразующих элементов по родительскому элементу
        $migration->createIndex('fias_parentguid_ix', FiasAddrobj::tableName(), [
            'parentguid',
            'formalname',
            'copy'
        ]);
    }

    /**
     * Удалить все исторические записи
     */
    public function actionRemoveHistory()
    {
        $migration = new Migration();
        $migration->db = Module::getInstance()->getDb();

        $addrobjTable = FiasAddrobj::tableName();
        $houseTable = FiasHouse::tableName();

        $migration->createIndex('fias_addrobj_currstatus_ix', FiasAddrobj::tableName(), ['currstatus']);
        $migration->execute("DELETE FROM {$addrobjTable} WHERE NOT(currstatus = 0)");
        $migration->dropIndex('fias_addrobj_currstatus_ix', FiasAddrobj::tableName());

        $migration->createIndex('fias_house_enddate_ix', FiasHouse::tableName(), ['enddate']);
        $migration->execute("DELETE FROM {$houseTable} WHERE enddate < now()");
        $migration->dropIndex('fias_house_enddate_ix', FiasHouse::tableName());
    }

    /**
     * Заполнить стоблец "полнотекстовый поиск"
     */
    public function actionBuildFillTextSearch()
    {
        echo "\nЗаполнение значений для полнотекстового поиска\n\n";

        $migration = new Migration();
        $migration->db = Module::getInstance()->getDb();
        $migration->dropIndex('fias_fulltext_search_ix', FiasAddrobj::tableName());
        $migration->update(FiasAddrobj::tableName(), ['fulltext_search' => null]);

        $totalCount = FiasAddrobj::find()->count();

        $processed = 0;
        $updateTime = 0;
        while ($processed < $totalCount) {
            $start = microtime(true);
            $addresses = FiasAddrobj::find()->orderBy('aoid')->offset($processed)->limit(1000)->all();
            foreach ($addresses as $address) {
                $address->getFulltextSearchIndexValue();
                $processed++;
            }
            $updateTime += microtime(true) - $start;

            echo sprintf(
                "\rОбработано %d из %d. Время обновления %f (среднее %f)",
                $processed,
                $totalCount,
                $updateTime,
                $updateTime / ($processed / 1000)
            );
        }

        // быстрый поиск актуальных адресообразующих элементов по полному наименованию
        $migration->createIndex('fias_fulltext_search_ix', FiasAddrobj::tableName(), [
            'regioncode',
            'fulltext_search',
            'copy'
        ]);
    }
}