<?php

namespace ejen\fias\console\controllers;

use yii\console\Controller;
use ejen\fias\Module;

class MigrateController extends Controller
{
    public function actionIndex()
    {
        exec(
            'php ' . \Yii::$app->basePath . '/yii migrate' .
            ' --interactive=0' .
            ' --migrationPath=' . Module::getInstance()->basePath . '/console/migrations' .
            ' --db=' . Module::getInstance()->db,
            $output
        );

        array_walk($output, function ($line) {
            echo sprintf("%s\n", $line);
        });
    }

    public function actionDown()
    {
        exec(
            'php ' . \Yii::$app->basePath . '/yii migrate/down' .
            ' --interactive=0' .
            ' --migrationPath=' . Module::getInstance()->basePath . '/console/migrations' .
            ' --db=' . Module::getInstance()->db . ' 100',
            $output
        );

        array_walk($output, function ($line) {
            echo sprintf("%s\n", $line);
        });
    }
}