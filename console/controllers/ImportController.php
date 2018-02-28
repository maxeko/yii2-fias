<?php

namespace ejen\fias\console\controllers;

use ejen\fias\common\models\FiasActstat;
use ejen\fias\common\models\FiasAddrobj;
use ejen\fias\common\models\FiasCenterst;
use ejen\fias\common\models\FiasCurentst;
use ejen\fias\common\models\FiasDaddrobj;
use ejen\fias\common\models\FiasDhouse;
use ejen\fias\common\models\FiasDhousint;
use ejen\fias\common\models\FiasDlandmrk;
use ejen\fias\common\models\FiasDnordoc;
use ejen\fias\common\models\FiasEststat;
use ejen\fias\common\models\FiasHouse;
use ejen\fias\common\models\FiasHouseint;
use ejen\fias\common\models\FiasHststat;
use ejen\fias\common\models\FiasIntvstat;
use ejen\fias\common\models\FiasLandmark;
use ejen\fias\common\models\FiasNordoc;
use ejen\fias\common\models\FiasOperstat;
use ejen\fias\common\models\FiasStrstat;
use ejen\fias\common\models\FiasSocrbase;

use ejen\fias\Module;
use yii\base\Exception;
use yii\console\Controller;
use yii\db\ActiveRecord;
use yii\db\Migration;
use yii\helpers\BaseFileHelper;
use yii\helpers\Console;

/**
 * Функции импорта данных в справочник ФИАС из обменных форматов
 */
class ImportController extends Controller
{
    /**
     * Буфер загрузки файла
     *
     * @var float
     */
    private $loadBuffer = 2.684e+8; // 256 Mib

    /**
     * Буфер импорта
     *
     * @var int
     */
    private $batchSize = 1000;

    /**
     * Отношение имён файлов к классам ФИАС
     *
     * @var array
     */
    private $classMap = [];

    /**
     * Перечисление колонок в выгрузке ГИСа "Реестр добавленных адресов в структурированном виде"
     *
     * @var array
     */
    private $gisColumns = [];

    /**
     * Отношение колонок ГИСа к ФИАСу
     *
     * @var array
     */
    private $gisColumnMap = [];

    /**
     * Колонки для определения копий
     *
     * @var array
     */
    private $gisCopies = [];

    /**
     * Обработчики данных из gis
     * принимает данные колонки и возвращает измененённые
     * для пропуска строки, нужно вернуть false
     *
     * @var array
     */
    private $gisDataProcessors = [];

    private $timeStamps = [];

    /**
     * @inheritdoc
     */
    public function __construct($id, Module $module, array $config = [])
    {
        $this->classMap = [
            'actstat'    => FiasActstat::className(),
            'addrobj'    => FiasAddrobj::className(),
            'addrob'     => FiasAddrobj::className(),
            'centerst'   => FiasCenterst::className(),
            'curentst'   => FiasCurentst::className(),
            'eststat'    => FiasEststat::className(),
            'house'      => FiasHouse::className(),
            'houseint'   => FiasHouseint::className(),
            'hststat'    => FiasHststat::className(),
            'intvstat'   => FiasIntvstat::className(),
            'landmark'   => FiasLandmark::className(),
            'nordoc'     => FiasNordoc::className(),
            'operstat'   => FiasOperstat::className(),
            'socrbase'   => FiasSocrbase::className(),
            'strstat'    => FiasStrstat::className(),
            'daddrobj'   => FiasDaddrobj::className(),
            'dhouse'     => FiasDhouse::className(),
            'dhousint'   => FiasDhousint::className(),
            'dlandmrk'   => FiasDlandmrk::className(),
            'dnordoc'    => FiasDnordoc::className()
        ];

        $this->gisColumns = [
            FiasAddrobj::tableName() => [
                "aoid", // 1
                "aoguid", // 2
                "formalname", // 3
                "shortname", // 4
                "aolevel", // 5
                "parentguid", // 6
                "regioncode", // 7
                "autocode", // 8
                "areacode", // 9
                "citycode", // 10
                "ctarcode", // 11
                "placecode", // 12
                "streetcode", // 13
                "extrcode", // 14
                "sextcode", // 15
                "postalcode", // 16
                "offname", // 17
                "oktmo", // 18
                "previd", // 19
                "nextid", // 20
                "updatedate", // 21
                "startdate", // 22
                "enddate", // 23
                "actual", // 24 Актуальность
                null, // 25 Дата создания записи
                null, // 26 Дата последнего изменения записи
                "fias_addrobjid", // 27
                "fias_addrobjguid", // 28
                null, // 29 Источник данных: «1» – из РЗИС; «2» – из ФИАС
                "gisgkh"
            ],
            FiasHouse::tableName() => [
                "houseid", // 1
                "houseguid", // 2
                "aoguid", // 3
                null, // 4 Уникальный идентификатор записи адресного объекта в ФИАС (при наличии связи с ФИАС)
                "housenum", // 5
                "buildnum", // 6
                "strucnum", // 7
                "eststatus", // 8
                "strstatus", // 9
                "oktmo", // 10
                "postalcode", // 11
                "updatedate", // 12
                "startdate", // 13
                "enddate", // 14
                "actual", // 15
                null, // 16 дата создания записи
                null, // 17 дата последнего изменения
                "fias_houseid", // 18 id для записи пришедшей из ФИАС. При наличии связи с ФИАС
                "fias_houseguid", // 19 Guid для записи пришедшей из ФИАС. При наличии связи с ФИАС
                "gisgkh_guid", // 20 Если запись является дублем, то заполняется корневым гуидом родительской записи
                null, // 21 Если дом является дочерним к агрегирующему дому, то заполняется его корневым гуидом
                null, // 22 Заполняется TRUE если дом используется в каком-либо реестре в ГИС ЖКХ, иначе – FALSE
                null, // 23 Заполняется TRUE если дом используется в каком-либо договоре в ГИС ЖКХ, иначе – FALSE
                null, // 24 Заполняется TRUE если дом используется в РАО, иначе FALSE
                null, // 25 Источник данных: «1» – из РЗИС; «2» – из ФИАС
                "gisgkh"
            ]
        ];

        $this->gisColumnMap = [
            FiasAddrobj::tableName() => [
                'aouid' => 'aoid',
                'start_dtae' => 'startdate',
                'fias_update_date' => 'updatedate',
                'start_date' => 'startdate',
                'end_date' => 'enddate'
            ],
            FiasHouse::tableName() => [
                'fias_update_date' => 'updatedate',
                'start_date' => 'startdate',
                'end_date' => 'enddate'
            ]
        ];

        $this->gisCopies = [
            FiasAddrobj::tableName() => [
                'fias_addrobjid' => 'aoid',
                'fias_addrobjguid' => 'aoguid'
            ],
            FiasHouse::tableName() => [
                'fias_houseid' => 'houseid',
                'fias_houseguid' => 'houseguid'
            ]
        ];

        $this->gisDataProcessors = [
            FiasHouse::tableName() => [
                'housenum' => function ($value) {
                    return strlen($value) > 20 ? false : $value;
                },
                'actual' => function ($value) {
                    return $value && ($value !== "f");
                }
            ],
            FiasAddrobj::tableName() => [
                'actual' => function ($value) {
                    return $value && ($value !== "f");
                }
            ],
        ];

        parent::__construct($id, $module, $config);
    }

    /**
     * Вот х/з че эта функция делает, пусть автор поикает
     * @param string $name
     * @param null $fromName
     * @return bool|float|null|string|string[]
     */
    private function ts($name, $fromName = null)
    {
        $prev = end($this->timeStamps);
        $this->timeStamps[$name] = microtime(true);

        if (is_string($fromName) && !empty($this->timeStamps[$fromName])) {
            return round($this->timeStamps[$name] - $this->timeStamps[$fromName], 2);
        }

        if ($fromName === '@last') {
            if ($prev !== false) {
                return round($this->timeStamps[$name] - $prev, 2);
            }
            return false;
        }

        $result = (string) round($this->timeStamps[$name], 2);

        if (strpos($result, ".")) {
            list($sec, $usec) = explode(".", $result);
        } else {
            $sec = (int) $result;
            $usec = "00";
        }

        return preg_replace("/^(00:)+/", "", gmdate("H:i:s", $sec) . ".{$usec}");
    }

    /**
     * По-умолчанию
     *
     * @return int
     */
    public function actionIndex()
    {
        return $this->actionFias();
    }

    /**
     * Импорт данных из ФИАС
     *
     * @return int
     */
    public function actionFias()
    {
        $fullStatistic = new \stdClass();

        $this->ts('init');

        $result = Module::getInstance()->getDb()->createCommand("SELECT * FROM system ORDER BY version DESC LIMIT 1")->query();
        $rows = $result->readAll();

        $fullStatistic->ts_detect_current_version = $this->ts('detect_current_version', 'init');

        if (!count($rows)) {
            $this->stdout("Текущая версия базы не известна. Необходимо внести запись в таблицу \"system\"\n");
            return 1;
        }

        // дата последней версии
        $currentDate = new \DateTime($rows[0]['version']);

        $this->stdout("Текущая версия {$rows[0]['version']}\n");

        $this->stdout("Загрузка версий ФИАС\n");
        $client = new \SoapClient('http://fias.nalog.ru/WebServices/Public/DownloadService.asmx?WSDL');

        try {
            /** @noinspection PhpUndefinedMethodInspection */
            $result = $client->GetAllDownloadFileInfo();
        } catch (\SoapFault $exception) {
            $this->stderr("Ошибка Soap сервиса ФИАС ({$exception->getMessage()})");
        }

        if (!$result) {
            $this->stderr("Не удалось загрузить версии\n");
            return 1;
        }

        $fullStatistic->ts_download_versions = $this->ts('download_versions', 'detect_current_version');

        $versions = $result->GetAllDownloadFileInfoResult->DownloadFileInfo;
        $dbfFilesCount = count($versions);

        if (!$dbfFilesCount) {
            $this->stdout("Загружен пустой список версий\n");
            return 1;
        }

        if ($currentDate >= new \DateTime(substr(end($versions)->TextVersion, -10)))
        {
            $this->stdout("Новых версий не обнаружено\n");
            return 0;
        }

        $this->stdout("Загружено {$dbfFilesCount} версий\n");

        $this->ts('processing_versions_init');

        $fullStatistic->ts_processing_versions = [];
        $fullStatistic->tables = [];

        foreach ($versions as $version) {
            $versionDate = new \DateTime(substr($version->TextVersion, -10));
            if ($versionDate > $currentDate) {

                $this->ts('version_processing_init');
                $fullStatistic->ts_processing_versions[] = $ts = new \stdClass();

                $dbFileName = basename($version->FiasDeltaDbfUrl);
                $localFilePath = "/tmp/{$dbFileName}";

                $headers = get_headers($version->FiasDeltaDbfUrl, 1);

                if ($headers[0] != "HTTP/1.1 200 OK") {
                    $this->stderr("Ошибка загрузки файла (" . $headers[0] . ")\n");
                    return 1;
                }

                $length = (int) $headers['Content-Length'];
                $buffer = "";
                $loaded = 0;

                $this->stdout("Импорт версии #{$version->VersionId} \"{$version->TextVersion}\" ($version->FiasDeltaDbfUrl " . round($length / (1024 * 1024), 2) . " Mib)\n");

                $fpRemote = fopen($version->FiasDeltaDbfUrl, 'rb');

                if (!$fpRemote) {
                    $this->stderr("Ошибка загрузки файла\n");
                    return 1;
                }

                $fpLocal = fopen($localFilePath, "wb");

                if (!$fpLocal) {
                    $this->stderr("Ошибка создания локального файла ({$localFilePath})\n");
                    return 1;
                }

                $this->stdout("Загрузка файла...\r");

                $output_ts = time();
                while (!feof($fpRemote)) {
                    $part = fread($fpRemote, 8192);
                    $buffer .= $part;
                    $loaded += strlen($part);

                    if (strlen($buffer) >= $this->loadBuffer || feof($fpRemote)) {
                        fwrite($fpLocal, $buffer, strlen($buffer));
                        $buffer = "";
                    }

                    $output_ts_time = time();
                    if ($output_ts_time > $output_ts)
                    {
                        $this->stdout("Загружено " . round($loaded / ($length / 100), 2) . "%  \r");
                        $output_ts = $output_ts_time;
                    }
                }

                fclose($fpRemote);
                fclose($fpLocal);

                $ts->download = $this->ts('download', 'version_processing_init');

                $this->stdout("Загружено " . round($loaded / ($length / 100), 2) . "%\n");

                $destination = "{$localFilePath}_extracted";
                $command = "unrar e -y {$localFilePath} *.DBF $destination/";

                $this->stdout("Извлечение файлов в {$destination}\n");

                exec($command, $output, $return);
                unlink($localFilePath);

                if ($return) {
                    $this->stderr("Ошибка извлечения\n");
                    $this->stderr("{$command}\n");
                    $this->stderr(join("\n", $output));
                    return 1;
                }

                $ts->extract = $this->ts('extract', 'download');

                $dbfFiles = scandir($destination);
                $dbfFilesCount = count($dbfFiles);

                if (2 >= $dbfFilesCount) {
                    $this->stderr("Нет файлов для импорта\n");
                    return 1;
                }

                for ($fileIndex = 2; $fileIndex < $dbfFilesCount; $fileIndex++)
                {
                    if (strpos(strtolower($dbfFiles[$fileIndex]), '.dbf') != strlen($dbfFiles[$fileIndex]) - 4) {
                        continue;
                    }

                    $dbFileName = "$destination/${dbfFiles[$fileIndex]}";

                    $this->stdout("\n{$dbFileName}\n");

                    if (strpos(strtolower($dbfFiles[$fileIndex]), 'nordoc') === 0) {
                        $this->stdout("Исключён из обработки");
                        // потому, что dbase_open не может открыть эти файлы и падает с ошибкой
                        // при этом функция не закрывает файловый дескриптор, что может привести
                        // PHP к выходу за лимит открытых дескрипторов и прекращению работы всего скрипта
                        continue;
                    }

                    $db = @dbase_open($dbFileName, 0);

                    // в зависимости от версии dbase может возвращать либо int, либо resource
                    if (!is_integer($db) && !is_resource($db)) {
                        $this->stderr("Не удалось открыть\n");
                        continue;
                    }

                    $modelClass = null;
                    foreach ($this->classMap as $name => $className) {
                        if (strpos(strtolower($dbfFiles[$fileIndex]), $name) !== false) {
                            $modelClass = $className;
                            break;
                        }
                    }

                    if ($modelClass === null) {
                        $this->stderr("Обработчик не определён\n");
                        dbase_close($db);
                        continue;
                    }

                    /* @var \yii\db\ActiveRecord $model */
                    $model = new $modelClass;

                    $columns = [];
                    $insertRows = [];
                    $primariesValues = [];

                    $primaries = $model->getPrimaryKey(true);

                    if (!count($primaries)) {
                        $this->stdout("В структуре таблицы \"{$modelClass::tableName()}\" не определены первичные ключи\n");
                        dbase_close($db);
                        continue;
                    }

                    $primaries = array_keys($primaries);

                    $deleted = 0;
                    $inserted = 0;
                    $time = 0;

                    $rowsCount = dbase_numrecords($db);

                    $this->ts('process_init');

                    $std = new \stdClass();
                    $std->rows = 0;
                    $std->time = 0.0;
                    $std->inserted = 0;
                    $std->updated = 0;

                    if ($rowsCount > 0)
                    {
                        if (empty($fullStatistic->tables[$model::tableName()]))
                        {
                            $fullStatistic->tables[$model::tableName()] = $std;
                        }

                        $std = $fullStatistic->tables[$model::tableName()];
                        $std->rows += $rowsCount;
                    }

                    for ($rowIndex = 0; $rowIndex < $rowsCount; $rowIndex++)
                    {
                        $row = dbase_get_record_with_names($db, $rowIndex + 1);

                        $insertRow = [];

                        foreach ($row as $column => $value) {
                            if ($column == 'delete') {
                                continue;
                            }

                            $column = strtolower($column);

                            if (!$model->hasAttribute($column)) {
                                continue;
                            }

                            $value = mb_convert_encoding($value, 'UTF-8', 'CP866');

                            if ($rowIndex == 0) {
                                $columns[] = strtolower($column);
                            }

                            if (in_array($column, $primaries)) {
                                $primariesValues[$column][] = $value;
                            }

                            $insertRow[] = $value;
                        }

                        $insertRows[] = $insertRow;

                        if (($rowIndex && ($rowIndex + 1) % $this->batchSize == 0) || $rowIndex == $rowsCount - 1)
                        {
                            if (count($primariesValues)) {
                                $deleted += $model->getDb()->createCommand()->delete($modelClass::tableName(), $primariesValues)->execute();
                            }

                            try {
                                $inserted += $model->getDb()->createCommand()->batchInsert($modelClass::tableName(), $columns, $insertRows)->execute();
                            } catch (Exception $e) {
                                $this->stderr($e->getMessage());
                            }
                            $primariesValues = [];
                            $insertRows = [];
                            $time = $this->ts('process_done', 'process_init');
                            $this->stdout("Обработано " . ($rowIndex + 1) . " из {$rowsCount} записей за {$time} сек. \r");
                        }
                    }

                    dbase_close($db);

                    $this->stdout("\n");

                    $std->updated += $deleted;
                    $std->inserted += $inserted - $deleted;
                    $std->time += $time;
                }

                $ts->import = $this->ts('import', 'extract');
                $ts->all = $this->ts('version_processing_done', 'version_processing_init');

                $versionFormatted = $versionDate->format('Y-m-d');
                Module::getInstance()->getDb()->createCommand("INSERT INTO system (version, date) VALUES ('{$versionFormatted}', NOW())")->execute();

                $this->stdout("\nИмпорт версии завершен за {$ts->all} сек. (загрузка: {$ts->download} сек., распаковка: {$ts->extract} сек., импорт: {$ts->import} сек.) \n");
                $this->stdout("Запись об импорте версии была добавлена в таблицу \"system\"\n");

                BaseFileHelper::removeDirectory($destination);

                /**
                 * Нужно накатывать только по одной дельте
                 * за запуск. Потому, что если дельт будет
                 * много, обновление может занять несколько
                 * дней
                 */
                break;
            }
        }

        $time = round($this->ts('processing_versions_done', 'processing_versions_init'), 2);
        $this->stdout("\nИмпорт всех версий завершен за {$time} сек.\n");
        $this->stdout("Импортировано новых версий: " . count($fullStatistic->ts_processing_versions) . "\n");

        foreach ($fullStatistic->tables as $name => $table)
        {
            $this->stdout("В таблицу {$name} было добавлено {$table->inserted} и обновлено {$table->updated} из {$table->rows} записей за {$table->time} сек.\n");
        }

        return 0;
    }

    /**
     * Импорт Реестра добавленных адресов (дельта) ГИС ЖКХ
     *
     * @param string $filename путь к ZIP архиву (содержащему ZIP архивы по регионам)
     * @return int
     */
    public function actionGis($filename)
    {
        $fullStatistic = new \stdClass();

        $this->ts('init');

        $extractDestinationFolder = "{$filename}_extracted";

        Console::output("Импорт данных из {$filename}");

        $zip = new \ZipArchive();
        $status = $zip->open($filename);

        if ($status !== true) {
            Console::error("Не удалось открыть архив \"{$filename}\" (status code: {$status})");
            return 1;
        }

        Console::output("В архиве обнаружено {$zip->numFiles} файлов");
        Console::output("Извлечение файлов в {$extractDestinationFolder}");

        $status = $zip->extractTo($extractDestinationFolder);

        if (!$status) {
            Console::error("Ошибка извлечения файлов");
            return 1;
        }

        $zip->close();

        $fullStatistic->extract = $this->ts('extract', 'init');

        $archiveFiles = scandir($extractDestinationFolder);
        $archiveFilesCount = count($archiveFiles);

        Console::output("Извлечено {$archiveFilesCount} архивов");

        // распаковка всех внутренних архивов

        $this->ts('sub_extracts_init');

        for ($fileIndex = 2; $fileIndex < $archiveFilesCount; $fileIndex++) {
            if (substr(strtolower($archiveFiles[$fileIndex]), -4) != ".zip") {
                continue;
            }

            $this->stdout("{$extractDestinationFolder}/{$archiveFiles[$fileIndex]}\n");

            if (!$zip->open("{$extractDestinationFolder}/{$archiveFiles[$fileIndex]}")) {
                Console::error("Не удалось открыть архив");
                continue;
            }

            Console::output("Извлечение в {$extractDestinationFolder}");
            $status = $zip->extractTo($extractDestinationFolder);

            if (!$status) {
                Console::error("Ошибка извлечения файлов");
                continue;
            }

            $zip->close();
        }

        $fullStatistic->subextracts = $this->ts('sub_extracts_done', 'sub_extracts_init');
        $fullStatistic->extractAll = $this->ts('extract_all', 'init');

        $CsvFiles = scandir($extractDestinationFolder);
        $CsvFilesCount = count($CsvFiles);

        $fullStatistic->tables = [];

        // обработка CSV файлов

        $this->ts('csv_process_init');

        for ($fileIndex = 2; $fileIndex < $CsvFilesCount; $fileIndex++) {
            if (substr(strtolower($CsvFiles[$fileIndex]), -4) != ".csv") {
                continue;
            }

            $filename = "{$extractDestinationFolder}/{$CsvFiles[$fileIndex]}";
            $modelClass = null;

            $this->stdout("\n{$filename}\n");

            // определение класса по имени файла

            foreach ($this->classMap as $name => $className) {
                if (strpos(strtolower($CsvFiles[$fileIndex]), $name) !== false) {
                    $modelClass = $className;
                    break;
                }
            }

            if (!$modelClass) {
                Console::error("Обработчик не определён");
                continue;
            }

            /* @var ActiveRecord $model */
            $model = new $modelClass;
            $db = $model::getDb();

            // первичные ключи таблицы
            $primaries = $model->getPrimaryKey(true);

            // данные для первичных ключей (для удаления дублей)
            $primariesValues = [];

//            if (empty($primaries)) {
//                Console::output("В структуре таблицы \"{$model::tableName()}\" не определены первичные ключи");
//                continue;
//            }

            $primaries = array_keys($primaries);

            $fp = fopen($filename, "r");

            $columns = array_values(array_filter($this->gisColumns[$model::tableName()]));
            $rows = [];
            $rowsCount = 0;

            $deleted = 0;
            $inserted = 0;
            $marked = 0;
            $time = 0;

            $fileSize = filesize($filename);

            $std = new \stdClass();
            $std->rows = 0;
            $std->inserted = 0;
            $std->updated = 0;
            $std->marked = 0;
            $std->time = 0.0;
            $std->skipped = 0;

            if (empty($fullStatistic->tables[$model::tableName()])) {
                $fullStatistic->tables[$model::tableName()] = $std;
            }

            $std = $fullStatistic->tables[$model::tableName()];

            $this->ts('process_init');

            // чтения CSV файла и обработка данных

            while (($data = fgetcsv($fp, 0, ";")) !== false) {
                $row = [];

                // определение данных для импорта

                for ($index = 0; $index < count($data); $index++) {
                    if (!isset($this->gisColumns[$model::tableName()][$index])) {
                        continue;
                    }

                    $column = $this->gisColumns[$model::tableName()][$index];
                    $value = $data[$index];

                    if ($column) {
                        if (is_callable(@$this->gisDataProcessors[$model::tableName()][$column])) {
                            $value = call_user_func($this->gisDataProcessors[$model::tableName()][$column], $value);

                            if ($value === false) {
                                $std->skipped++;
                                continue 2;
                            }
                        }

                        $row[] = $value;

                        // если текущая колонка определена как первичная
                        // сохраняем данные этой колонки для удаления дублей
                        if (in_array($column, $primaries)) {
                            $primariesValues[$column][] = $value;
                        }
                    }
                }

                // данные для дополнительной колонки "gisgkh"
                $row[] = true;

                $rows[] = $row;
                $rowsCount++;

                // импортируем, если буфер импорта полон или достигнут конец файла

                // функция feof не всегда возвращает true когда при достижении конца файла
                $eof = feof($fp) || $fileSize == ftell($fp);

                if ($rowsCount % $this->batchSize == 0 || $eof) {
                    //if (count($primariesValues)) {
                        // удаление дублирующихся записей, для последующей замены новыми
                        //$deleted += $db->createCommand()->delete($model::tableName(), $primariesValues)->execute();
                    //}

                    $inserted += $db->createCommand()->batchInsert(
                        $model::tableName(),
                        array_filter($columns),
                        $rows
                    )->execute();

                    $time = $this->ts('process_done', 'process_init');

                    $rows = [];
                    $primariesValues = [];

                    $this->stdout("Обработано {$rowsCount} записей за {$time} сек.\r");

                    if ($eof) {
                        $this->stdout("\n");
                    }
                }
            }

            fclose($fp);

            if ($rowsCount == 0) {
                Console::output("Нет данных");
            }

            $std->rows += $rowsCount;
            $std->inserted += $inserted - $deleted;
            $std->updated += $deleted;
            $std->marked += $marked;
            $std->time += $time;
        }

        $fullStatistic->process_done = $this->ts('csv_process_done', 'csv_process_init');
        $fullStatistic->done = $this->ts('done', 'init');

        Console::output(sprintf(
            "Импорт завершен за %d сек. (распаковка архивов: %d сек., импорт: %d сек.)",
            $fullStatistic->done,
            $fullStatistic->extractAll,
            $fullStatistic->process_done
        ));

        foreach ($fullStatistic->tables as $name => $table) {
            Console::output(sprintf(
                "В таблицу %s было добавлено %d и обновлено %d записей из %d за %s сек. ",
                $name,
                $table->inserted,
                $table->updated,
                $table->rows,
                $table->time
            ));
            Console::output(sprintf(
                "Копией было помечено %d записей. Пропущено %d записей",
                $table->marked,
                $table->skipped
            ));
        }

        BaseFileHelper::removeDirectory($extractDestinationFolder);

        return 0;
    }

    public function actionImportDbf($filename)
    {
        $db = @dbase_open($filename, 0);
        if (!$db)
        {
            $this->stderr("Не удалось открыть DBF файл: '$filename'\n");
            return 1;
        }

        $classMap = [
            '/^.*DADDROBJ\.DBF$/'   => FiasDaddrobj::className(),
            '/^.*ADDROBJ\.DBF$/'    => FiasAddrobj::className(),
            '/^.*LANDMARK\.DBF$/'   => FiasLandmark::className(),
            '/^.*DHOUSE\.DBF$/'     => FiasDhouse::className(),
            '/^.*HOUSE\d\d\.DBF$/'  => FiasHouse::className(),
            '/^.*DHOUSINT\.DBF$/'   => FiasDhousint::className(),
            '/^.*HOUSEINT\.DBF$/'   => FiasHouseint::className(),
            '/^.*DLANDMRK\.DBF$/'   => FiasDlandmrk::className(),
            '/^.*DNORDOC\.DBF$/'    => FiasDnordoc::className(),
            '/^.*NORDOC\d\d\.DBF$/' => FiasNordoc::className(),
            '/^.*ACTSTAT\.DBF$/'    => FiasActstat::className(),
            '/^.*CENTERST\.DBF$/'   => FiasCenterst::className(),
            '/^.*ESTSTAT\.DBF$/'    => FiasEststat::className(),
            '/^.*HSTSTAT\.DBF$/'    => FiasHststat::className(),
            '/^.*OPERSTAT\.DBF$/'   => FiasOperstat::className(),
            '/^.*INTVSTAT\.DBF$/'   => FiasIntvstat::className(),
            '/^.*STRSTAT\.DBF$/'    => FiasStrstat::className(),
            '/^.*CURENTST\.DBF$/'   => FiasCurentst::className(),
            '/^.*SOCRBASE\.DBF$/'   => FiasSocrbase::className(),
        ];

        $modelClass = false;
        foreach($classMap as $pattern => $className)
        {
            if (preg_match($pattern, $filename))
            {
                $modelClass = $className;
                break;
            }
        }

        if ($modelClass === false)
        {
            $this->stderr("Не поддерживаемый DBF файл: '$filename'\n");
            return 1;
        }


        $rowsCount = dbase_numrecords($db);
        $this->stdout("Записей в DBF файле '$filename' : $rowsCount\n");

        $j = 0;
        $insertRows = [];
        
        for ($i = 1; $i <= $rowsCount; $i++)
        {

            $row = dbase_get_record_with_names($db, $i);

            /* @var \yii\db\ActiveRecord $model */
            $model = new $modelClass;
            
            $insertRow = [];
            $columns = [];
            foreach($row as $key => $value)
            {
                
                if ($key == 'deleted') continue;
                $key = strtolower($key);
                
                
                if ($model->hasAttribute($key)) {
                    $columns[] = $key;
                    $insertRow[] = trim(mb_convert_encoding($value, 'UTF-8', 'CP866'));
                }
            }
            
            $insertRows[] = $insertRow;
            
            $j++;
            
            if ($j == 1000)
            {
                $transaction = Module::getInstance()->getDb()->beginTransaction();
                //var_dump($insertRows); die();
                Module::getInstance()->getDb()->createCommand()->batchInsert($modelClass::tableName(), $columns, $insertRows)->execute();
                $insertRows = [];
                
                $transaction->commit();
                $j = 0;
                $this->stdout("Обработано $i из $rowsCount записей\n");
            }
        }

        if (!empty($insertRows)) {
            $transaction = Module::getInstance()->getDb()->beginTransaction();
            Module::getInstance()->getDb()->createCommand()->batchInsert($modelClass::tableName(), $columns, $insertRows)->execute();
            $insertRows = [];
            $transaction->commit();
        }

        /*if ($j != 0)
        {
            $transaction->commit();
        }*/
        return 0;
    }

    /**
     * Удалить записи с дублирующимся PK
     */
    public function actionRemoveDuplicates()
    {
        $migration = new Migration();
        $migration->db = Module::getInstance()->getDb();

        $houseTable = FiasHouse::tableName();
        $houseCopyTable = "fias_house_copy";

        // создаём копию таблицы объектов адресации без дублей
        $migration->execute("CREATE TABLE {$houseCopyTable} AS SELECT DISTINCT ON (houseid) * FROM {$houseTable}");

        // удаляем старую таблица с объектами адресации
        $migration->execute("DROP TABLE " . FiasHouse::tableName());

        // заменяем старую таблицу с объектами адресации таблицей без дублей
        $migration->execute("ALTER TABLE {$houseCopyTable} RENAME TO {$houseTable}");
    }
}
