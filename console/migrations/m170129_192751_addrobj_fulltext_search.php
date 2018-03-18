<?php

use yii\db\Migration;

use ejen\fias\common\models\FiasAddrobj;

class m170129_192751_addrobj_fulltext_search extends Migration
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
        $this->addColumn(FiasAddrobj::tableName(), 'fulltext_search', $this->string(1024)->null());
        $this->addColumn(FiasAddrobj::tableName(), 'fulltext_search_upper', $this->string(1024)->null());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn(FiasAddrobj::tableName(), 'fulltext_search');
        $this->dropColumn(FiasAddrobj::tableName(), 'fulltext_search_upper');
    }
}
