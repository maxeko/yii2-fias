<?php

namespace ejen\fias\console\controllers;

use yii\console\Controller;
use ejen\fias\Module;

/**
 * Работа с миграциями модуля
 *
 * Применить все миграции:
 *
 * ```
 * ./yii fias/migrate
 * ```
 *
 * Откатить все миграции:
 *
 * ```
 * ./yii fias/migrate/down
 * ```
 *
 * Создать новую миграцию:
 *
 * ```
 * ./yii fias/migrate/create
 * ```
 */
class MigrateController extends Controller
{
    /**
     * Применить миграции модуля
     */
    public function actionIndex()
    {
        $this->run('/migrate', [
            'interactive' => false,
            'migrationPath' => Module::getInstance()->basePath . '/console/migrations',
            'db' => Module::getInstance()->db
        ]);
    }

    /**
     * Откатить миграции модуля
     * @param int $count
     */
    public function actionDown($count = 100)
    {
        $this->run('/migrate/down', [
            'interactive' => false,
            'migrationPath' => Module::getInstance()->basePath . '/console/migrations',
            'db' => Module::getInstance()->db,
            $count
        ]);
    }

    /**
     * Создать миграцию для модуля
     * @param string $migrationName
     */
    public function actionCreate($migrationName)
    {
        $this->run('/migrate/create', [
            'interactive' => false,
            'migrationPath' => Module::getInstance()->basePath . '/console/migrations',
            $migrationName
        ]);
    }
}