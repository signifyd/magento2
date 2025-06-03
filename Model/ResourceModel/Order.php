<?php

namespace Signifyd\Connect\Model\ResourceModel;

class Order extends \Magento\Sales\Model\ResourceModel\Order
{
    /**
     * @var bool
     */
    public $loadForUpdate = false;

    /**
     * Load for update method.
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @param mixed $value
     * @param string $field
     * @return Order
     */
    public function loadForUpdate(\Magento\Framework\Model\AbstractModel $object, $value, $field = null)
    {
        $this->loadForUpdate = true;

        return parent::load($object, $value, $field);
    }

    /**
     * Get load select method.
     *
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
