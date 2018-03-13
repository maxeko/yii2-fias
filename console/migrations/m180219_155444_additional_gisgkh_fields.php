<?php

use ejen\fias\common\models\FiasAddrobj;
use ejen\fias\common\models\FiasHouse;
use yii\db\Migration;
use ejen\fias\Module as FiasModule;

/**
 * Дополнительные поля для таблиц fias_addrobj и fias_house
 * для хранения статуса строки из реестра временных кдресов ГИС ЖКХ
 *
 * fias_addrobj:
 *  + actual
 *      false если элемент был деактуализирован в ФИАС или
 *      временный элемент был деактуализирован в рамках работ по выравниванию адресного справочника
 *  - copy
 *      не используемое поле
 *
 * fias_house:
 *  + actual
 *      false если адрес был деактуализирован в ФИАС или
 *      временный адрес был деактуализирован в рамках работ по выравниванию адресного справочника
 *  + gisgkh_guid
 *      если запись является дублем временного адреса в реестре добавленных адресов ГИС ЖКХ,
 *      то заполняется корневым гуидом родительской записи, иначе – пусто
 *  - copy
 *      не используемое поле
 */
class m180219_155444_additional_gisgkh_fields extends Migration
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->db = FiasModule::getInstance()->getDb();

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(FiasAddrobj::tableName(), "actual", $this->boolean()->notNull()->defaultValue(true));
        $this->addColumn(FiasHouse::tableName(), "actual", $this->boolean()->notNull()->defaultValue(true));
        $this->addColumn(FiasHouse::tableName(), "gisgkh_guid", $this->string()->null());
        $this->dropColumn(FiasAddrobj::tableName(), "copy");
        $this->dropColumn(FiasHouse::tableName(), "copy");
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->addColumn(FiasAddrobj::tableName(), "copy", $this->boolean()->defaultValue(false));
        $this->addColumn(FiasHouse::tableName(), "copy", $this->boolean()->defaultValue(false));
        $this->dropColumn(FiasAddrobj::tableName(), "actual");
        $this->dropColumn(FiasHouse::tableName(), "actual");
        $this->dropColumn(FiasHouse::tableName(), "gisgkh_guid");
    }
}
