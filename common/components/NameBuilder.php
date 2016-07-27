<?php

namespace ejen\fias\common\components;

use yii\base\Component;
use yii\db\ActiveRecord;

use ejen\fias\common\models\FiasAddrobj;
use ejen\fias\common\models\FiasHouse;

class NameBuilder extends Component
{
    /**
     * @param FiasHouse|FiasAddrobj|string $fiasObjectOrGuid
     * @param string $separator
     * @return string
     */
    public function buildFullName($fiasObjectOrGuid, $separator = ", ")
    {
        $fiasObject = null;
        $parent = null;
        $name = '';

        if (is_string($fiasObjectOrGuid)) {
            $fiasGuid = $fiasObjectOrGuid;

            $fiasObject =
                FiasHouse::findOne(['houseguid' => $fiasGuid]) ?:
                FiasAddrobj::findOne(['aoguid' => $fiasGuid, 'currstatus' => 0]);

        } elseif (is_subclass_of($fiasObjectOrGuid, ActiveRecord::className())) {
            $fiasObject = $fiasObjectOrGuid;
        }

        if (is_a($fiasObject, FiasHouse::className())) {
            /* @var FiasHouse $fiasObject */

            /* @var FiasAddrobj $parent */
            $parent = $fiasObject->addrobj;
            $name = $fiasObject->housenum;

        } elseif (is_a($fiasObject, FiasAddrobj::className())) {
            /* @var FiasAddrobj $fiasObject */

            /* @var FiasAddrobj $parent */
            $parent = $fiasObject->getParent()->where(['currstatus' => 0])->one();
            $name = $fiasObject->getName();

        }

        if ($parent)
        {
            $name = $this->buildFullName($parent, $separator).$separator.$name;
        }

        return $name;
    }
}