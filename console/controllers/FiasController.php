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

class FiasController extends Controller
{
    public $region;

    public $versions;

    public function options($actionID)
    {
        return [
            'region',
        ];
    }

    private function loadVersions()
    {
        $client = new \SoapClient('http://fias.nalog.ru/WebServices/Public/DownloadService.asmx?WSDL');
        $this->versions = $client->GetAllDownloadFileInfo();
    }

    public function actionImportDbfFolder($path)
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

    public function actionImportDbfFile($filename)
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

        /* @var \yii\db\ActiveRecord $model */
        $modelClass = null;
        foreach($classMap as $pattern => $className)
        {
            if (preg_match($pattern, $filename))
            {
                $modelClass = $className;
                break;
            }
        }

        if ($modelClass === null)
        {
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

        if (!count($primaries))
        {
            $this->stdout("В структуре таблицы \"{$modelClass::tableName()}\" не определены первичные ключи\n");
            return 0;
        }

        $primaries = array_keys($primaries);

        $deleted = 0;
        $inserted = 0;

        for ($i = 0; $i < $rowsCount; $i++)
        {
            $row = dbase_get_record_with_names($db, $i + 1);

            $insertRow = [];

            foreach ($row as $column => $value)
            {
                if ($column == 'delete')
                {
                    continue;
                }

                $column = strtolower($column);

                if (!$model->hasAttribute($column))
                {
                    continue;
                }

                $value = mb_convert_encoding($value, 'UTF-8', 'CP866');

                if ($i == 0)
                {
                    $columns[] = strtolower($column);
                }

                if (in_array($column, $primaries)) {
                    $primariesValues[$column][] = $value;
                }

                $insertRow[] = $value;
            }

            $insertRows[] = $insertRow;

            if (($i && $i % $batchSize == 0) || $i == $rowsCount - 1) {
                $deleted+= $model->getDb()->createCommand()->delete($modelClass::tableName(), $primariesValues)->execute();
                $inserted+= $model->getDb()->createCommand()->batchInsert($modelClass::tableName(), $columns, $insertRows)->execute();
                $primariesValues = [];
                $insertRows = [];
                $this->stdout("Обработано " . ($i + 1) . " из $rowsCount записей\r");
            }
        }

        $this->stdout("\nОбновлено $deleted, добавлено " . ($inserted - $deleted) . "\n");

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
