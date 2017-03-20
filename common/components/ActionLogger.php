<?php

namespace ejen\fias\common\components;

use yii\base\ActionEvent;
use yii\base\Component;
use yii\base\Controller;
use yii\helpers\Console;

/**
 * Класс для вывода отчетов о выполнении
 */
class ActionLogger extends Component
{
    private $stepsCount = 1;

    private $actionName = '';
    private $moduleId = '';
    private $controllerId = '';
    private $actionId = '';
    private $isConsole = true;
    private $startTime = null;

    private $itemsCount = 0;
    private $itemsProcessed = 0;

    private $step = 0;
    private $stepName = '';
    private $stepStartTime = null;
    private $stepExecutionTime = 0;
    private $stepErrors = 0;

    private $errors = [];
    private $statistics = [];
    private $statisticsPath = null;

    private $executionTime = 0;

    /**
     * @inheritdoc
     */
    public function __construct(array $config = [])
    {
        $this->moduleId = (\Yii::$app->module ? \Yii::$app->module->id : null);
        $this->controllerId = \Yii::$app->controller->id;
        $this->actionId = \Yii::$app->controller->action->id;
        $this->isConsole = (\Yii::$app instanceof \yii\console\Application);
        $this->statisticsPath = \Yii::getAlias("@runtime/action_logger_statistics.json");

        $this->readStatistics();

        parent::__construct($config);
    }

    /**
     * Действие запущено
     * @param string $name текстовое описание выполняемого действия
     * @param int $stepsCount количество шагов
     * @return $this
     */
    public function action($name, $stepsCount = 1)
    {
        $this->stepsCount = $stepsCount;
        $this->step = 0;
        $this->actionName = $name;

        $line = str_repeat('-', mb_strlen($this->actionName));

        \Yii::$app->controller->stdout(
            "\n{$this->actionName}\n{$line}\n\n",
            Console::FG_CYAN
        );

        \Yii::$app->controller->on(Controller::EVENT_AFTER_ACTION, [$this, 'actionCompleted']);

        $this->startTime = time();
        $this->stepStartTime = $this->startTime;

        return $this;
    }

    /**
     * Количество обрабатываемых элементов, для оценки статуса выполнения (если задан шаг, то для данного шага)
     * @param string $count
     */
    public function setItemsCount($count)
    {
        $this->itemsCount = $count;
    }

    /**
     * Запущен шаг
     * @param string $name краткое опиание шага, для вывода в консоль
     * @return $this
     */
    public function step($name = null)
    {
        $this->step++;
        $this->stepName = $name;
        $this->stepErrors = 0;
        $this->stepStartTime = time();
        $this->stepExecutionTime = 0;
        $this->itemsProcessed = 0;
        $this->itemsCount = 0;

        $stepTitle = sprintf(
            "%s%s",
            $this->stepsCount > 1 ? sprintf("Шаг %d из %d. ", $this->step, $this->stepsCount) : '',
            $this->stepName ?: ''
        );

        if ($stepTitle) {
            $line = str_repeat('-', mb_strlen($stepTitle));
            \Yii::$app->controller->stdout("\n{$stepTitle}\n{$line}\n\n", Console::FG_YELLOW);
        }

        return $this;
    }

    /**
     * Работа завершена. Если задан шаг, то для данного шага
     */
    public function completed()
    {
        if ($this->stepsCount > 1 || $this->stepName) {
            $this->stepExecutionTime = time() - $this->stepStartTime;

            if (!$this->stepErrors) {
                \Yii::$app->controller->stdout("\n\nЗавершено без ошибок\n", Console::FG_YELLOW);
            } else {
                \Yii::$app->controller->stdout("\n\nЗавершено с ошибками\n", Console::FG_YELLOW);
            }

            \Yii::$app->controller->stdout(
                sprintf("Время выполнения: %s\n", $this->formatTime($this->stepExecutionTime)),
                Console::FG_YELLOW
            );

            $stepHash = md5(strval($this->step) . $this->stepName);

            $midExecutionTime = @$this->statistics[$stepHash]['midExecutionTime'] ?: 0;
            $numberOfExecutions =  @$this->statistics[$stepHash]['numberOfExecutions'] ?: 0;
            $this->statistics[$stepHash]['numberOfExecutions'] = $numberOfExecutions + 1;
            $this->statistics[$stepHash]['midExecutionTime'] = intdiv(
                $midExecutionTime * $numberOfExecutions + $this->stepExecutionTime,
                $numberOfExecutions + 1
            );

            if ($numberOfExecutions) {
                \Yii::$app->controller->stdout(
                    sprintf("Среднее время выполнения в этом окружении: %s\n", $this->formatTime($this->statistics[$stepHash]['midExecutionTime'])),
                    Console::FG_YELLOW
                );
            }
        }
    }

    /**
     * Обновить количество обработанных элементов, для вывода текущего статуса обработки в консоль
     * @param integer $itemsProcessed
     */
    public function updateStatus($itemsProcessed)
    {
        if ($itemsProcessed) {
            \Yii::$app->controller->stdout("\r");
        }

        $this->itemsProcessed = $itemsProcessed;
        $this->stepExecutionTime = time() - $this->stepStartTime;

        $percentage = intdiv(100 * $this->itemsProcessed, $this->itemsCount);
        $timeLeft = $percentage ? intval(100 * $this->stepExecutionTime / $percentage) - $this->stepExecutionTime : 0;

        \Yii::$app->controller->stdout(sprintf(
            "Обработано %d из %d (%d%%). Время выполнения %s" . ($timeLeft ? ", осталось ~ %s" : "             ") . "             ",
            $this->itemsProcessed,
            $this->itemsCount,
            $percentage,
            $this->formatTime($this->stepExecutionTime),
            $this->formatTime($timeLeft)
        ), Console::FG_GREY);
    }

    /**
     * Произошла ошибка
     * @param string $description
     * @param bool $fatal
     */
    public function errorOccurred($description, $fatal = false)
    {
        if (!$fatal) {
            \Yii::$app->controller->stdout("\nПрервано из-за ошибки\n\n", Console::FG_RED);
            \Yii::$app->controller->stdout("{$description}\n\n", Console::FG_GREY);
        }

        $this->errors[] = $description;
        \Yii::$app->controller->stdout("\nОшибка:\n{$description}\n", Console::FG_GREY);

        if ($this->step) {
            $this->stepErrors++;
        }
    }

    /**
     * Завершение action
     * @param ActionEvent $event
     */
    public function actionCompleted(ActionEvent $event)
    {
        if ($this->itemsProcessed) {
            \Yii::$app->controller->stdout("\r");
        }

        $this->executionTime = time() - $this->startTime;

        if ($event->action->id != $this->actionId) return;

        if (empty($this->errors)) {
            \Yii::$app->controller->stdout(
                "\n\nВыполнено без ошибок\n",
                Console::FG_CYAN
            );
        } else {
            \Yii::$app->controller->stdout(
                "\n\nВыполнено с ошибками\n",
                Console::FG_PURPLE
            );
        }

        \Yii::$app->controller->stdout(
            sprintf("Общее время выполнения: %s\n", $this->formatTime($this->executionTime)),
            Console::FG_CYAN
        );

        $midExecutionTime = @$this->statistics['midExecutionTime'] ?: 0;
        $numberOfExecutions =  @$this->statistics['numberOfExecutions'] ?: 0;
        $this->statistics['numberOfExecutions'] = $numberOfExecutions + 1;
        $this->statistics['midExecutionTime'] = intdiv(
            $midExecutionTime * $numberOfExecutions + $this->executionTime,
            $numberOfExecutions + 1
        );

        if ($numberOfExecutions) {
            \Yii::$app->controller->stdout(
                sprintf("Среднее время выполнения в этом окружении: %s\n", $this->formatTime($this->statistics['midExecutionTime'])),
                Console::FG_CYAN
            );
        }

        \Yii::$app->controller->stdout("\n\n");
        $this->saveStatistics();
    }

    /**
     * Временной интервал в секундах привести к виду "%H ч. %M мин. %S сек."
     * @param $timeInSeconds
     * @return string
     */
    private function formatTime($timeInSeconds)
    {
        $hours = intdiv(intdiv($timeInSeconds, 60), 60);
        $minutes = intdiv($timeInSeconds % 3600, 60);
        $seconds = $timeInSeconds % 60;

        $parts = [
            $hours ? sprintf("%d ч.", $hours) : '',
            $minutes ? sprintf("%d мин.", $minutes) : '',
            $seconds ? sprintf("%d сек.", $seconds) : '',
        ];

        $parts = array_filter($parts);
        return join(' ', $parts);
    }

    /**
     * Прочитать статистические значения (среднее время выполнения действия и отдельных шагов)
     */
    private function readStatistics()
    {
        if (file_exists($this->statisticsPath)) {
            $fullStatistics = json_decode(file_get_contents($this->statisticsPath), true) ?: [];

            if ($this->moduleId) {
                $this->statistics = @$fullStatistics[$this->moduleId][$this->controllerId][$this->actionId] ?: [];
            } else {
                $this->statistics = @$fullStatistics[$this->controllerId][$this->actionId] ?: [];
            }
        }
    }

    /**
     * Обновить статистику с учетом текущих результатов
     */
    public function saveStatistics()
    {
        if (file_exists($this->statisticsPath)) {
            $fullStatistics = json_decode(file_get_contents($this->statisticsPath), true) ?: [];
        } else {
            $fullStatistics = [];
        }

        if ($this->moduleId) {
            $fullStatistics[$this->moduleId][$this->controllerId][$this->actionId] = $this->statistics;
        } else {
            $fullStatistics[$this->controllerId][$this->actionId] = $this->statistics;
        }

        file_put_contents($this->statisticsPath, json_encode($fullStatistics, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}