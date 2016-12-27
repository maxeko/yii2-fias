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
use yii\console\Controller;
use yii\db\ActiveRecord;

class ImportController extends Controller
{
    private $loadBuffer = 2.684e+8; // 256 Mib

    private $batchSize = 1000;

    private $classMap = [];

    private $gisColumnMap = [];

    private $gisCopies = [];

    function __construct($id, Module $module, array $config = [])
    {
        $this->classMap = [
            'actstat'    => FiasActstat::className(),
            'addrobj'    => FiasAddrobj::className(),
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
            'dnordoc'    => FiasDnordoc::className()];

        $this->gisColumnMap = [
            FiasAddrobj::tableName() => [
                'aouid' => 'aoid',
                'start_dtae' => 'startdate',
                'fias_update_date' => 'updatedate',
                'start_date' => 'startdate',
                'end_date' => 'enddate'],
            FiasHouse::tableName() => [
                'fias_update_date' => 'updatedate',
                'start_date' => 'startdate',
                'end_date' => 'enddate']
        ];

        $this->gisCopies = [
            FiasAddrobj::tableName() => [
                'fias_addrobjid' => 'aoid',
                'fias_addrobjguid' => 'aoguid'],
            FiasHouse::tableName() => [
                'fias_houseid' => 'houseid',
                'fias_houseguid' => 'houseguid']
        ];

        parent::__construct($id, $module, $config);
    }

    public function actionFias()
    {
        $db = Module::getInstance()->getDb();

        $result = $db->createCommand("SELECT * FROM system WHERE type = 'FIAS' ORDER BY date ASC LIMIT 1")->query();
        $rows = $result->readAll();

        if (!count($rows)) {
            $this->stdout("Текущая версия базы не известна. Необходимо внести запись в таблицу \"system\"\n");
            return 0;
        }

        // дата последней версии
        $currentDate = new \DateTime($rows[0]['version']);

        $this->stdout("Загрузка версий ФИАС\n");
        $client = new \SoapClient('http://fias.nalog.ru/WebServices/Public/DownloadService.asmx?WSDL');
        $result = $client->GetAllDownloadFileInfo();

        if (!$result) {
            $this->stderr("Не удалось загрузить версии\n");
            return 1;
        }

        $versions = $result->GetAllDownloadFileInfoResult->DownloadFileInfo;
        $len = count($versions);

        if (!$len) {
            $this->stdout("Новых версий не найдено\n");
            return 0;
        }

        $this->stdout("Версий загружено: {$len}\n");

        foreach ($versions as $version) {
            $versionDate = new \DateTime(substr($version->TextVersion, -10));
            if ($versionDate > $currentDate) {
                $this->stdout("Импорт версии #{$version->VersionId} \"{$version->TextVersion}\" ($version->FiasDeltaDbfUrl)\n");

                $filename = basename($version->FiasDeltaDbfUrl);
                $path = "/tmp/{$filename}";

                $headers = get_headers($version->FiasDeltaDbfUrl, 1);

                if ($headers[0] != "HTTP/1.1 200 OK") {
                    $this->stderr("Ошибка загрузки файла (" . $headers[0] . ")\n");
                    return 1;
                }

                $length = (int) $headers['Content-Length'];
                $data = "";
                $loaded = 0;

                $fp = fopen($version->FiasDeltaDbfUrl, 'rb');

                if (!$fp) {
                    $this->stderr("Ошибка загрузки файла\n");
                    return 1;
                }

                $dfp = fopen($path, "wb");

                if (!$dfp) {
                    $this->stderr("Ошибка создания локального файла");
                    return 1;
                }

                $this->stdout("Загрузка файла...\r");

                while (!feof($fp)) {
                    $part = fread($fp, 1000);
                    $data .= $part;
                    $loaded += strlen($part);

                    if (strlen($data) >= $this->loadBuffer || feof($fp)) {
                        fwrite($dfp, $data, strlen($data));
                        $data = "";
                    }

                    $this->stdout("Загружено " . round($loaded / ($length / 100), 2) . "%  \r");
                }

                fclose($fp);
                fclose($dfp);

                $this->stdout("Загружено " . round(($loaded / 1048576), 2) . " Мб\n");

                $destination = "{$path}_extracted";
                $command = "unrar e -y {$path} *.DBF $destination/";

                $this->stdout("Извлечение файлов в {$destination}\n");

                exec($command, $output, $return);

                if ($return) {
                    $this->stderr("Ошибка извлечения\n");
                    $this->stderr("{$command}\n");
                    $this->stderr(join("\n", $output));
                    return 1;
                }

                $files = scandir($destination);
                $len = count($files);

                if (2 >= $len) {
                    $this->stderr("Директория пуста\n");
                    return 1;
                }

                for ($fileIndex = 2; $fileIndex < $len; $fileIndex++) {
                    $filename = "$destination/${files[$fileIndex]}";

                    $this->stdout("\n{$filename}\n");

                    if (!strpos(strtolower($files[$fileIndex]), '.dbf')) {
                        $this->stdout("Неправильное расширение\n");
                        continue;
                    }

                    $db = @dbase_open($filename, 0);

                    if (!$db) {
                        $this->stderr("Не удалось открыть\n");
                        continue;
                    }

                    $classMap = [
                        '/^.*DADDROBJ\.DBF$/' => FiasDaddrobj::className(),
                        '/^.*ADDROBJ\.DBF$/' => FiasAddrobj::className(),
                        '/^.*LANDMARK\.DBF$/' => FiasLandmark::className(),
                        '/^.*DHOUSE\.DBF$/' => FiasDhouse::className(),
                        '/^.*HOUSE\d\d\.DBF$/' => FiasHouse::className(),
                        '/^.*DHOUSINT\.DBF$/' => FiasDhousint::className(),
                        '/^.*HOUSEINT\.DBF$/' => FiasHouseint::className(),
                        '/^.*DLANDMRK\.DBF$/' => FiasDlandmrk::className(),
                        '/^.*DNORDOC\.DBF$/' => FiasDnordoc::className(),
                        '/^.*NORDOC\d\d\.DBF$/' => FiasNordoc::className(),
                        '/^.*ACTSTAT\.DBF$/' => FiasActstat::className(),
                        '/^.*CENTERST\.DBF$/' => FiasCenterst::className(),
                        '/^.*ESTSTAT\.DBF$/' => FiasEststat::className(),
                        '/^.*HSTSTAT\.DBF$/' => FiasHststat::className(),
                        '/^.*OPERSTAT\.DBF$/' => FiasOperstat::className(),
                        '/^.*INTVSTAT\.DBF$/' => FiasIntvstat::className(),
                        '/^.*STRSTAT\.DBF$/' => FiasStrstat::className(),
                        '/^.*CURENTST\.DBF$/' => FiasCurentst::className(),
                        '/^.*SOCRBASE\.DBF$/' => FiasSocrbase::className(),
                    ];

                    /* @var \yii\db\ActiveRecord $model */
                    $modelClass = null;
                    foreach ($classMap as $pattern => $className) {
                        if (preg_match($pattern, $filename)) {
                            $modelClass = $className;
                            break;
                        }
                    }

                    if ($modelClass === null) {
                        $this->stderr("Обработчик не определён\n");
                        continue;
                    }

                    $this->batchSize = 1000;
                    $rowsCount = dbase_numrecords($db);
                    $this->stdout("Колчичество записей: {$rowsCount}\n");

                    /* @var \yii\db\ActiveRecord $model */
                    $model = new $modelClass;

                    $columns = [];
                    $insertRows = [];
                    $primariesValues = [];

                    $primaries = $model->getPrimaryKey(true);

                    if (!count($primaries)) {
                        $this->stdout("В структуре таблицы \"{$modelClass::tableName()}\" не определены первичные ключи\n");
                        continue;
                    }

                    $primaries = array_keys($primaries);

                    $deleted = 0;
                    $inserted = 0;

                    for ($rowIndex = 0; $rowIndex < $rowsCount; $rowIndex++) {
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

                        if (($rowIndex && ($rowIndex + 1) % $this->batchSize == 0) || $rowIndex == $rowsCount - 1) {
                            $deleted += $model->getDb()->createCommand()->delete($modelClass::tableName(), $primariesValues)->execute();
                            $inserted += $model->getDb()->createCommand()->batchInsert($modelClass::tableName(), $columns, $insertRows)->execute();
                            $primariesValues = [];
                            $insertRows = [];
                            $this->stdout("Обработано " . ($rowIndex + 1) . " из $rowsCount записей\r");
                        }
                    }

                    $this->stdout("\nОбновлено $deleted, добавлено " . ($inserted - $deleted) . "\n");
                }
            }
        }

        return 0;
    }

    public function actionGis($filename)
    {
        $destination = "{$filename}_extracted";
        $zip = new \ZipArchive();
        $status = $zip->open($filename);
        if ($status !== true)
        {
            $this->stderr("Не удалось открыть архив \"{$filename}\"\n");
            $this->stderr($status);
            return 1;
        }

        $this->stdout("Извлечение файлов в {$destination}\n");
        $status = $zip->extractTo($destination);

        if (!$status)
        {
            $this->stderr("Ошибка извлечения файлов\n");
            return 1;
        }

        $zip->close();

        $files = array_slice(scandir($destination), 2);
        $len = count($files);

        $this->stdout("Извлечено {$len} архивов\n");

        for ($fileIndex = 0; $fileIndex < $len; $fileIndex++)
        {
            if (substr(strtolower($files[$fileIndex]), -4) != ".zip")
            {
                continue;
            }

            $this->stdout("{$destination}/{$files[$fileIndex]}\n");

            $zip = new \ZipArchive();

            if (!$zip->open("{$destination}/{$files[$fileIndex]}"))
            {
                $this->stderr("Не удалось открыть архив\n");
                continue;
            }

            $this->stdout("Извлечение в {$destination}\n");
            $status = $zip->extractTo($destination);

            if (!$status)
            {
                $this->stderr("Ошибка извлечения файлов\n");
                continue;
            }
        }

        $files = scandir($destination);
        $len = count($files);

        for ($fileIndex = 2; $fileIndex < $len; $fileIndex++)
        {
            if (substr(strtolower($files[$fileIndex]), -4) != ".csv")
            {
                continue;
            }

            $filename = "{$destination}/{$files[$fileIndex]}";
            $modelClass = null;

            $this->stdout("\n{$filename}\n");

            foreach ($this->classMap as $name => $className)
            {
                if (strpos(strtolower($files[$fileIndex]), $name) !== false)
                {
                    $modelClass = $className;
                    break;
                }
            }

            if (!$modelClass)
            {
                $this->stderr("Обработчик не определён\n");
                continue;
            }

            /* @var ActiveRecord $model */
            $model = new $modelClass;
            $db = $model->getDb();

            $primaries = $model->getPrimaryKey(true);
            $primariesValues = [];
            $copyIndexes = [];
            $copies = [];

            if (!count($primaries))
            {
                $this->stdout("В структуре таблицы \"{$model::tableName()}\" не определены первичные ключи\n");
                continue;
            }

            $primaries = array_keys($primaries);

            $fp = fopen($filename, "r");

            $columns = null;
            $rows = [];
            $rowsCount = 0;

            $deleted = 0;
            $inserted = 0;
            $marked = 0;

            $fileSize = filesize($filename);

            while (($data = fgetcsv($fp, 0, ";")) !== false)
            {
                if (!$columns)
                {
                    $columns = [];

                    foreach ($data as $index => $item)
                    {
                        if (!empty($this->gisCopies[$model::tableName()][$item]))
                        {
                            $copyIndexes[$index] = $this->gisCopies[$model::tableName()][$item];
                        }

                        $item = empty($this->gisColumnMap[$model::tableName()][$item]) ?
                            $item : $this->gisColumnMap[$model::tableName()][$item];
                        $columns[] = $item && $model->hasAttribute($item) ? $item : "";
                    }

                    // extra column
                    $columns[] = "gisgkh";
                    continue;
                }

                $row = [];

                for ($index = 0; $index < count($data); $index++)
                {
                    $column = $columns[$index];
                    $value = $data[$index];

                    if ($column)
                    {
                        $row[] = $value;

                        if (in_array($column, $primaries))
                        {
                            $primariesValues[$column][] = $value;
                        }
                    } else if (!empty($copyIndexes[$index])) {
                        $copies[$copyIndexes[$index]][] = $value;
                    }
                }

                // data for extra column "gisgkh"
                $row[] = 'TRUE';

                $rows[] = $row;
                $rowsCount++;

                if ($rowsCount % $this->batchSize == 0 || feof($fp) || $fileSize == ftell($fp)) {

                    if (count($primariesValues))
                    {
                        $deleted += $db->createCommand()->delete($model::tableName(), $primariesValues)->execute();
                    }

                    $inserted += $db->createCommand()->batchInsert($model::tableName(), array_filter($columns), $rows)->execute();
                    $marked += $db->createCommand()->update($model::tableName(), [ 'copy' => 'TRUE' ], $copies)->execute();

                    $rows = [];
                    $primariesValues = [];
                    $copies = [];

                    $this->stdout("Обработано {$rowsCount} записей\r");
                }
            }

            $this->stdout("\nОбновлено $deleted, добавлено " . ($inserted - $deleted) . ", помечено копией {$marked}\n");

            fclose($fp);
        }

        return 0;
    }

    public function actionUpdateGis($path)
    {
        $this->type = ImportController::TYPE_GIS;

        $this->actionFromZip($path);
    }

    public function actionFromUrl($url)
    {
        $filename = basename($url);
        $path = "/tmp/{$filename}";

        $headers = get_headers($url, 1);

        if ($headers[0] != "HTTP/1.1 200 OK") {
            $this->stderr("Ошибка загрузки файла (" . $headers[0] . ")\n");
            return 1;
        }

        $length = (int)$headers['Content-Length'];
        $data = "";
        $loaded = 0;

        $fp = fopen($url, 'rb');

        if (!$fp) {
            $this->stderr("Ошибка загрузки файла\n");
            return 1;
        }

        $dfp = fopen($path, "wb");

        if (!$dfp) {
            $this->stderr("Ошибка создания локального файла");
            return 1;
        }

        $this->stdout("Загрузка файла...");

        while (!feof($fp)) {
            $part = fread($fp, 1000);
            $data .= $part;
            $loaded += strlen($part);

            if (strlen($data) >= 2.684e+8 || feof($fp)) {
                fwrite($dfp, $data, strlen($data));
                $data = "";
            }

            $this->stdout("\rЗагружено " . round($loaded / ($length / 100), 2) . "%");
        }

        fclose($fp);
        fclose($dfp);

        $this->stdout("\rЗагружено " . round(($loaded / 1048576), 2) . " Мб\n");

        $this->actionFromRar($path);

        return 0;
    }

    public function actionFromRar($filename)
    {
        $destination = "{$filename}_extracted";
        $command = "unrar e -y {$filename} *.DBF $destination/";

        $this->stdout("Извлечение файлов в {$destination}\n");

        exec($command, $output, $return);

        if ($return) {
            $this->stderr("Ошибка извлечения\n");
            $this->stderr("{$command}\n");
            $this->stderr(join("\n", $output));
            return 1;
        }

        $this->actionFromFolder($destination);

        return 0;
    }

    public function actionFromZip($filename)
    {
        $destination = "{$filename}_extracted";
        $zip = new \ZipArchive();
        $status = $zip->open($filename, \ZipArchive::EXCL);

        if ($status !== true) {
            $this->stderr("Ошибка открытия ZIP архива\n");
            $this->stderr($zip);
            return 1;
        }

        $this->stdout("Извлечение файлов в {$destination}");
        $status = $zip->extractTo($destination);

        if (!$status) {
            $this->stderr('Ошибка извлечения файлов');
            return 1;
        }

        $zip->close();

        $this->actionFromFolder($destination);

        return 0;
    }

    public function actionFromFolder($path)
    {
        $path = realpath($path);

        if (!$path) {
            $this->stderr("Неправильный путь\n");
            return 1;
        }

        if (!is_readable($path)) {
            $this->stderr("Нет прав на чтение директории\n");
            return 1;
        }

        $files = scandir($path);
        $len = count($files);

        if (2 >= $len) {
            $this->stderr("Директория пуста\n");
            return 1;
        }

        for ($i = 2; $i < $len; $i++) {
            $filename = "$path/${files[$i]}";

            if (!strpos(strtolower($files[$i]), '.dbf')) {
                $this->stdout("Файл \"$filename\" пропущен\n");
                continue;
            }

            $this->actionImportDbf($filename);
        }

        return 0;
    }

    public function actionFromDbfFile($filename)
    {
        $db = @dbase_open($filename, 0);

        if (!$db) {
            $this->stderr("Не удалось открыть DBF файл: '$filename'\n");
            return 1;
        }

        $classMap = [
            '/^.*DADDROBJ\.DBF$/' => FiasDaddrobj::className(),
            '/^.*ADDROBJ\.DBF$/' => FiasAddrobj::className(),
            '/^.*LANDMARK\.DBF$/' => FiasLandmark::className(),
            '/^.*DHOUSE\.DBF$/' => FiasDhouse::className(),
            '/^.*HOUSE\d\d\.DBF$/' => FiasHouse::className(),
            '/^.*DHOUSINT\.DBF$/' => FiasDhousint::className(),
            '/^.*HOUSEINT\.DBF$/' => FiasHouseint::className(),
            '/^.*DLANDMRK\.DBF$/' => FiasDlandmrk::className(),
            '/^.*DNORDOC\.DBF$/' => FiasDnordoc::className(),
            '/^.*NORDOC\d\d\.DBF$/' => FiasNordoc::className(),
            '/^.*ACTSTAT\.DBF$/' => FiasActstat::className(),
            '/^.*CENTERST\.DBF$/' => FiasCenterst::className(),
            '/^.*ESTSTAT\.DBF$/' => FiasEststat::className(),
            '/^.*HSTSTAT\.DBF$/' => FiasHststat::className(),
            '/^.*OPERSTAT\.DBF$/' => FiasOperstat::className(),
            '/^.*INTVSTAT\.DBF$/' => FiasIntvstat::className(),
            '/^.*STRSTAT\.DBF$/' => FiasStrstat::className(),
            '/^.*CURENTST\.DBF$/' => FiasCurentst::className(),
            '/^.*SOCRBASE\.DBF$/' => FiasSocrbase::className(),
        ];

        /* @var \yii\db\ActiveRecord $model */
        $modelClass = null;
        foreach ($classMap as $pattern => $className) {
            if (preg_match($pattern, $filename)) {
                $modelClass = $className;
                break;
            }
        }

        if ($modelClass === null) {
            $this->stderr("Не поддерживаемый DBF файл: '$filename'\n");
            return 1;
        }

        $batchSize = 1000;
        $rowsCount = dbase_numrecords($db);
        $this->stdout("Записей в DBF файле '$filename' : $rowsCount\n");

        /* @var \yii\db\ActiveRecord $model */
        $model = new $modelClass;

        $columns = [];
        $insertRows = [];
        $primariesValues = [];

        $primaries = $model->getPrimaryKey(true);

        if (!count($primaries)) {
            $this->stdout("В структуре таблицы \"{$modelClass::tableName()}\" не определены первичные ключи\n");
            return 0;
        }

        $primaries = array_keys($primaries);

        $deleted = 0;
        $inserted = 0;

        for ($i = 0; $i < $rowsCount; $i++) {
            $row = dbase_get_record_with_names($db, $i + 1);

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

                if ($i == 0) {
                    $columns[] = strtolower($column);
                }

                if (in_array($column, $primaries)) {
                    $primariesValues[$column][] = $value;
                }

                $insertRow[] = $value;
            }

            $insertRows[] = $insertRow;

            if (($i && $i % $batchSize == 0) || $i == $rowsCount - 1) {
                $deleted += $model->getDb()->createCommand()->delete($modelClass::tableName(), $primariesValues)->execute();
                $inserted += $model->getDb()->createCommand()->batchInsert($modelClass::tableName(), $columns, $insertRows)->execute();
                $primariesValues = [];
                $insertRows = [];
                $this->stdout("Обработано " . ($i + 1) . " из $rowsCount записей\r");
            }
        }

        $this->stdout("\nОбновлено $deleted, добавлено " . ($inserted - $deleted) . "\n");

        return 0;
    }

    public function actionFromCsvFile($filename)
    {

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
            '/^.*ESTSTAT\.DBF$/'    => FiasDhousint::className(),
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

            if ($modelClass == FiasAddrobj::className() && $this->region && intval($row['REGIONCODE']) != intval($this->region))
            {
                continue;
            }

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
}
