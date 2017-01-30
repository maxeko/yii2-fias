<?php

namespace ejen\fias;
use ejen\fias\common\components\ActionLogger;
use ejen\fias\common\components\NameBuilder;

/**
 * Class Module
 *
 * @property NameBuilder $nameBuilder
 * @property ActionLogger $actionLogger
 */
class Module extends \yii\base\Module implements \yii\base\BootstrapInterface
{
    /* @var string $db name of the component to use for database access */
    public $db = 'db';
    
    public $fiasHouseTable = 'fias_house';
    public $fiasAddrobjTable = 'fias_addrobj';

    /**
     * @var string NameBuilder component ID, set to false if not needed
     */
    public $nameBuilderComponent = 'fiasNameBuilder';

    public function init()
    {
        parent::init();

        $config = require(__DIR__ . '/config.php');

        \Yii::configure($this, $config);
    }

    /**
     * @return \yii\db\Connection the database connection.
     */
    public function getDb()
    {
        return \Yii::$app->{$this->db};
    }

    /**
     * @inheritdoc
     */
    public function bootstrap($app)
    {
        $moduleId = $this->id;

        if (!empty($this->nameBuilderComponent) && $this->nameBuilderComponent) {
            $app->set($this->nameBuilderComponent, $this->nameBuilder);
        }

        if ($app instanceof \yii\web\Application) {
            $this->controllerNamespace = 'ejen\fias\web\controllers';
        } elseif ($app instanceof \yii\console\Application) {
            $this->controllerNamespace = 'ejen\fias\console\controllers';
        }
    }

}