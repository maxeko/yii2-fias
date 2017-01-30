<?php

use yii\db\Migration;

use ejen\fias\common\models\FiasAddrobj;

/**
 * Количество домов в подчинении у адресообразующего элемента
 */
class m170130_134445_addrobj_houses_count extends Migration
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $module = ejen\fias\Module::getInstance();

        if (!empty($module)) {
            $this->db = $module->getDb();
        }

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(FiasAddrobj::tableName(), 'houses_count', $this->integer()->notNull()->defaultValue(0));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn(FiasAddrobj::tableName(), 'houses_count');
    }
}
