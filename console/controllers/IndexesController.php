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
use yii\helpers\Console;

/**
 * Работа с индексами из консоли
 */
class IndexesController extends Controller
{
    public function actionDrop()
    {
        $logger = Module::getInstance()->actionLogger;
        $logger->action('Удаление всех индексов базы ФИАС');

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

    public function actionBuild()
    {
        $this->runAction("drop");

        $logger = Module::getInstance()->actionLogger;
        $logger->action("Построение индексов для базы ФИАС", 7);

        $migration = new Migration();
        $migration->db = Module::getInstance()->getDb();

        $logger->step("Первичный ключ для таблицы адресообразующих элементов");
        $migration->addPrimaryKey("fias_addrobj_aoid_pk", FiasAddrobj::tableName(), ["aoid"]);
        $logger->completed();

        $logger->step('Индекс для поиска объекта адресации по GUID');
        $migration->createIndex('fias_house_houseguid_ix', FiasHouse::tableName(), [
            "houseguid",
            "enddate",
            "gisgkh",
            "gisgkh_guid",
        ]);
        $logger->completed();

        $logger->step('Индекс для поиска объектов адресации по адресообразующему элементу и "номеру дома"');
        $migration->createIndex('fias_house_housenum_aoguid_enddate_copy_ix', FiasHouse::tableName(), [
            "aoguid",
            "actual",
            "housenum",
            "buildnum",
            "strucnum",
            "gisgkh",
            "gisgkh_guid",
        ]);
        $logger->completed();

        $logger->step('Индекс для быстрого поиска актуального адресообразующего элемента по GUID');
        $migration->createIndex('fias_addrobj_aoguid_ix', FiasAddrobj::tableName(), [
            'aoguid',
            'enddate',
            'currstatus',
            'fias_addrobjguid'
        ]);
        $logger->completed();

        $logger->step('Индекс для быстрого поиска актуальных адресообразующих элементов по названию и уровню');
        $migration->createIndex('fias_addrobj_aolevel_ix', FiasAddrobj::tableName(), [
            'aolevel',
            'formalname',
            'actual',
            'gisgkh',
            'fias_addrobjguid'
        ]);
        $logger->completed();

        $logger->step('Индекс для быстрого поиска актуальных адресообразующих элементов по родительскому элементу');
        $migration->createIndex('fias_addrobj_parentguid_ix', FiasAddrobj::tableName(), [
            'parentguid',
            'formalname',
            'actual',
            'gisgkh',
            'fias_addrobjguid'
        ]);
        $logger->completed();

        $logger->step('Индекс для поиска адресообразующих элементов по полному названию');
        $migration->createIndex('fias_addrobj_fulltext_search_ix', FiasAddrobj::tableName(), [
            'regioncode',
            'fulltext_search',
            'houses_count',
            'actual',
            'gisgkh',
            'fias_addrobjguid'
        ]);

        $logger->step('Индекс для поиска адресообразующих элементов по полному названию 2');
        $migration->createIndex('fias_addrobj_fulltext_search_upper_ix', FiasAddrobj::tableName(), [
            'regioncode',
            'fulltext_search_upper',
            'houses_count',
            'actual',
            'gisgkh',
            'fias_addrobjguid'
        ]);

        $logger->completed();
    }

    public function actionBuildCachedFields()
    {
        $this->runAction("drop");

        $logger = Module::getInstance()->actionLogger;
        $logger->action("Заполнение кэширующих полей для базы ФИАС", 5);

        $migration = new Migration();
        $migration->db = Module::getInstance()->getDb();

        $logger->step("Установка признака актуальности для адресообразующих элементов");
        $migration->createIndex('fias_addrobj_currstatus_ix', FiasAddrobj::tableName(), ["currstatus"]);

        // http://wiki.gis-lab.info/w/%D0%A4%D0%98%D0%90%D0%A1#.D0.A1.D1.82.D0.B0.D1.82.D1.83.D1.81_.D0.B0.D0.BA.D1.82.D1.83.D0.B0.D0.BB.D1.8C.D0.BD.D0.BE.D1.81.D1.82.D0.B8
        $migration->update("fias_addrobj", ["actual"  => false], [
            "and",
            ["not", ["currstatus" => 0]],
            ["gisgkh" => false]
        ]);
        $migration->dropIndex('fias_addrobj_currstatus_ix', FiasAddrobj::tableName());
        $logger->completed();

        $logger->step("Установка признака актуальности для адресов");
        $migration->createIndex('fias_house_enddate_ix', FiasHouse::tableName(), ["enddate"]);
        $migration->update("fias_house", ["actual"  => false], [
            "and",
            ["<", "enddate", 'NOW()'],
            ["gisgkh" => false]
        ]);
        $migration->dropIndex('fias_house_enddate_ix', FiasHouse::tableName());
        $logger->completed();

        $logger->step("Установка связи ФИАС GUID-ов с GUID-ами рекомендуемыми к использованию в ГИС ЖКХ");
        $migration->createIndex('fias_house_houseguid_ix', FiasHouse::tableName(), ["actual", "gisgkh", "houseguid", "fias_houseguid"]);
        $migration->execute(<<<SQL
UPDATE fias_house SET gisgkh_guid = t.guid FROM (
    SELECT houseguid, fias_houseguid, (CASE WHEN gisgkh_guid IS NULL THEN houseguid ELSE gisgkh_guid END) AS guid FROM fias_house
    WHERE gisgkh = true AND actual = true AND NOT(fias_houseguid IS NULL)
) AS t WHERE
fias_house.houseguid = t.fias_houseguid AND
NOT(t.houseguid = t.guid) AND
gisgkh = false;
SQL
        );
        $migration->dropIndex('fias_house_houseguid_ix', FiasHouse::tableName());
        $logger->completed();

        $logger->step('Обновление кэша "количество подчинённых адресов"');
        $migration->createIndex('fias_addrobj_aougid_ix', FiasAddrobj::tableName(), ["aoguid"]);
        $migration->createIndex('fias_house_aougid_ix', FiasHouse::tableName(), ["actual", "aoguid", "houseguid"]);
        $migration->execute(<<<SQL
UPDATE fias_addrobj SET houses_count = t.count FROM (
    SELECT aoguid, count(houseguid) AS count
    FROM fias_house
    WHERE actual = true
    GROUP BY aoguid
) AS t WHERE t.aoguid = fias_addrobj.aoguid;
SQL
        );
        $migration->dropIndex('fias_addrobj_aougid_ix', FiasAddrobj::tableName());
        $migration->dropIndex('fias_house_aougid_ix', FiasHouse::tableName());
        $logger->completed();

        $logger->step('Обновление кэша "полное название адресообразующего элемента"');

        $migration->addPrimaryKey('fias_addrobj_pk', FiasAddrobj::tableName(), ["aoid"]);
        $migration->createIndex('fias_addrobj_parent_ix', FiasAddrobj::tableName(), [
            "aoguid",
            "parentguid",
            "currstatus",
            "nextid"
        ]);

        $query = FiasAddrobj::find()->select([
            "aoid",
            "fulltext_search",
            "formalname",
            "shortname",
            "parentguid"
        ])->orderBy('aoid');

        $count = $query->count();
        $processed = 0;
        Console::startProgress($processed, $count, "Обработано: ", false);
        /**
         * @var FiasAddrobj[] $addresses
         */
        foreach ($query->batch() as $addresses) {
            foreach ($addresses as $address) {
                $address->getFulltextSearchIndexValue();
                Console::updateProgress(++$processed, $count);
            }
        }
        Console::endProgress();

        $migration->dropIndex('fias_addrobj_parent_ix', FiasAddrobj::tableName());
        $migration->dropPrimaryKey('fias_addrobj_pk', FiasAddrobj::tableName());

        $logger->completed();
    }

    /**
     * Построить все индексы
     */
    public function actionBuildAll()
    {
        $logger = Module::getInstance()->actionLogger;
        $logger->action('Построение индексов для базы ФИАС', 9);

        $migration = new Migration();
        $migration->db = Module::getInstance()->getDb();

        $logger->step('Первичный ключ для таблицы объектов адресации');
        $migration->addPrimaryKey("fias_house_houseid_pk", FiasHouse::tableName(), ['houseid']);
        $logger->completed();

        $logger->step('Индекс для поиска объекта адресации по GUID');
        $migration->createIndex('fias_house_houseguid_enddate_copy_ix', FiasHouse::tableName(), [
            "houseguid",
            "enddate",
            "copy"
        ]);
        $logger->completed();

        $logger->step('Индекс для поиска объектов адресации по адресообразующему элементу и "номеру дома"');
        $migration->createIndex('fias_house_housenum_aoguid_enddate_copy_ix', FiasHouse::tableName(), [
            "housenum",
            "aoguid",
            "enddate",
            "copy"
        ]);
        $logger->completed();

        $logger->step('Индекс для поиска объектов адресации по адресообразующему элементу');
        $migration->createIndex('fias_house_aoguid_enddate_copy_ix', FiasHouse::tableName(), [
            "aoguid",
            "enddate",
            "copy"
        ]);
        $logger->completed();

        $logger->step('Первичный ключ для таблицы адресообразующих элементов');
        $migration->addPrimaryKey("fias_addrobj_aoid_pk", FiasAddrobj::tableName(), ['aoid']);
        $logger->completed();

        $logger->step('Индекс для быстрого поиска актуального адресообразующего элемента по GUID');
        $migration->createIndex('fias_aoguid_ix', FiasAddrobj::tableName(), [
            'aoguid',
            'copy',
            'currstatus'
        ]);
        $logger->completed();

        $logger->step('Индекс для быстрого поиска актуальных адресообразующих элементов по названию и уровню');
        $migration->createIndex('fias_aolevel_ix', FiasAddrobj::tableName(), [
            'aolevel',
            'formalname',
            'copy',
            'currstatus'
        ]);
        $logger->completed();

        $logger->step('Индекс для быстрого поиска актуальных адресообразующих элементов по родительскому элементу');
        $migration->createIndex('fias_parentguid_ix', FiasAddrobj::tableName(), [
            'parentguid',
            'formalname',
            'copy',
            'currstatus'
        ]);
        $logger->completed();

        $logger->step('Индекс для поиска адресообразующих элементов по полному названию');
        $this->buildFulltextSearchIndex();
        $logger->completed();
    }

    /**
     * Удалить все исторические записи
     * @deprecated
     */
    public function actionRemoveHistory()
    {
        $logger = Module::getInstance()->actionLogger;
        $logger->action('Удаление историческиз записей', 7);
        $migration = new Migration();
        $migration->db = Module::getInstance()->getDb();
        $addrobjTable = FiasAddrobj::tableName();
        $houseTable = FiasHouse::tableName();

        $logger->step('Создание временного индекса для работы с таблицей адресообрзазующих элементов');
        $migration->createIndex('fias_addrobj_currstatus_ix', FiasAddrobj::tableName(), ['currstatus']);
        $logger->completed();

        $logger->step('Удаление исторических записей из таблицы адресообрзазующих элементов');
        $migration->execute("DELETE FROM {$addrobjTable} WHERE NOT(currstatus = 0)");
        $logger->completed();

        $logger->step('Удаление подчинённых адресообразующих элементов, оставшихся без родителя ("битые" записи)');
        $migration->execute("
          DELETE FROM {$addrobjTable} 
          WHERE aoid IN (
            SELECT a2.aoid 
            FROM {$addrobjTable} AS a2 
            LEFT OUTER JOIN {$addrobjTable} AS a1 ON a2.parentguid = a1.aoguid 
            WHERE a1.aoid IS NULL AND NOT(a2.aolevel = 1)
          );
        ");
        $logger->completed();

        $logger->step('Удаление временного индекса для работы с таблицей адресообрзующих элементов');
        $migration->dropIndex('fias_addrobj_currstatus_ix', FiasAddrobj::tableName());
        $logger->completed();

        $logger->step('Создание временного индекса для работы с таблицей объектов адресации');
        $migration->createIndex('fias_house_enddate_ix', FiasHouse::tableName(), ['enddate']);
        $logger->completed();

        $logger->step('Удаление исторических записей из таблицы объектов адресации');
        $migration->execute("DELETE FROM {$houseTable} WHERE enddate < now()");
        $logger->completed();

        $logger->step('Удаление временного индекса для работы с таблицей объектов адресации');
        $migration->dropIndex('fias_house_enddate_ix', FiasHouse::tableName());
        $logger->completed();

    }

    /**
     * Построение индекса для поиска по полному названию адресообразующего элемента
     */
    public function actionRebuildFulltextSearchIndex()
    {
        $logger = Module::getInstance()->actionLogger;
        $logger->action('Построение индекса для поиска по полному названию адресообразующего элемента', 4);

        $migration = new Migration();
        $migration->db = Module::getInstance()->getDb();

        $logger->step('Удаление индекса');
        $this->dropFulltextSearchIndex();
        $logger->completed();


        $logger->step('Очиска старого кэша');
        $migration->update(FiasAddrobj::tableName(), ['fulltext_search' => null]);
        $logger->completed();

        $logger->step('Заполнение кэша полных названий');
        $totalCount = FiasAddrobj::find()->count();
        $logger->setItemsCount($totalCount);
        $processed = 0;
        while ($processed < $totalCount) {
            $addresses = FiasAddrobj::find()->orderBy('aoid')->offset($processed)->limit(1000)->all();
            foreach ($addresses as $address) {
                $address->getFulltextSearchIndexValue();
                $processed++;
            }
            $logger->updateStatus($processed);
        }
        $logger->completed();

        $logger->step('Построение индекса');
        $this->buildFulltextSearchIndex();
        $logger->completed();
    }

    /**
     * Обновление кэша "количество подчинённых адресов"
     */
    public function actionRebuildHousesCountCache()
    {
        $logger = Module::getInstance()->actionLogger;
        $logger->action('Обновление кэша "количество подчинённых адресов"');

        $migration = new Migration();
        $migration->db = Module::getInstance()->getDb();

        $migration->execute("
          UPDATE fias_addrobj SET houses_count = t.count FROM (
            SELECT aoguid, count(houseid) AS count
            FROM fias_house
            WHERE actual = true 
            GROUP BY aoguid
          ) AS t WHERE t.aoguid = fias_addrobj.aoguid;
        ");
    }

    /**
     * Создание индексов необходимых для импорта дампа ФИАС
     */
    public function actionBuildFiasImport()
    {
        $logger = Module::getInstance()->actionLogger;
        $logger->action('Построение индексов обновления ФИАС', 2);

        $migration = new Migration();
        $migration->db = Module::getInstance()->getDb();

        $logger->step('Первичный ключ для таблицы объектов адресации');
        $migration->addPrimaryKey("fias_house_houseid_pk", FiasHouse::tableName(), ['houseid']);
        $logger->completed();

        $logger->step('Первичный ключ для таблицы адресообразующих объектов');
        $migration->addPrimaryKey("fias_addrobj_aoid_pk", FiasAddrobj::tableName(), ['aoid']);
        $logger->completed();
    }

    /**
     * Создание индексов необходимых для импорта дельты ГИС ЖКХ
     */
    public function actionBuildGisDelta()
    {
        $logger = Module::getInstance()->actionLogger;
        $logger->action('Построение индексов для импорта дельты ГИС ЖКХ', 4);

        $migration = new Migration();
        $migration->db = Module::getInstance()->getDb();

        $logger->step('Первичный ключ для таблицы объектов адресации');
        $migration->addPrimaryKey("fias_house_houseid_pk", FiasHouse::tableName(), ['houseid']);
        $logger->completed();

        $logger->step('Индекс (houseid, houseguid)');
        $tableName = FiasHouse::tableName();
        $migration->execute("CREATE INDEX \"fias_house_houseid_houseguid_ix\" ON {$tableName} (\"houseid\", \"houseguid\", \"copy\")");
        $logger->completed();

        $logger->step('Первичный ключ (aoid)');
        $migration->addPrimaryKey("fias_addrobj_aoid_pk", FiasAddrobj::tableName(), ['aoid']);
        $logger->completed();

        $logger->step('Индекс (aoid, aoguid)');
        $tableName = FiasAddrobj::tableName();
        $migration->execute("CREATE INDEX \"fias_addrobj_aoid_aoguid_ix\" ON {$tableName} (\"aoid\", \"aoguid\", \"copy\")");
        $logger->completed();
    }

    /**
     * Удалить индекс для понотестового поиска адресов
     */
    private function dropFulltextSearchIndex()
    {
        $migration = new Migration();
        $migration->db = Module::getInstance()->getDb();

        try {
            $migration->dropIndex('fias_addrobj_fulltext_search_ix', FiasAddrobj::tableName());
        } catch (Exception $exception) {
            // ничего страшного :)
        }
    }

    /**
     * Постоить индекс для понотестового поиска адресов
     */
    private function buildFulltextSearchIndex()
    {
        $migration = new Migration();
        $migration->db = Module::getInstance()->getDb();
        $migration->createIndex('fias_addrobj_fulltext_search_ix', FiasAddrobj::tableName(), [
            'regioncode',
            'fulltext_search',
            'houses_count',
            'actual'
        ]);
    }
}