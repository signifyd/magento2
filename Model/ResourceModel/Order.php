<?php

namespace Signifyd\Connect\Model\ResourceModel;

class Order extends \Magento\Sales\Model\ResourceModel\Order
{
    /**
     * @var bool
     */
    protected $loadForUpdate = false;

    public function loadForUpdate(\Magento\Framework\Model\AbstractModel $object, $value, $field = null)
    {
        $this->loadForUpdate = true;

        return parent::load($object, $value, $field);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return \Magento\Framework\DB\Select|mixed
     */
    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);

        if ($this->loadForUpdate) {
            $select->forUpdate(true);
        }

        return $select;
    }
}
