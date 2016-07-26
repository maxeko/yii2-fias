<?php

namespace ejen\fias;

class Module extends \yii\base\Module implements \yii\base\BootstrapInterface
{
    /* @var string $db name of the component to use for database access */
    public $db = 'db';

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

        if ($app instanceof \yii\web\Application) {
            //
        } elseif ($app instanceof \yii\console\Application) {
            $this->controllerNamespace = 'ejen\fias\console\controllers';
        }
    }

}