<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * ORM model declaration for case data
 */
class Casedata extends AbstractDb
{
    /**
     * @var bool
     */
    protected $loadForUpdate = false;

    /**
     * Initialize connection and define main table
     */
    protected function _construct()
    {
        $this->_init('signifyd_connect_case', 'order_increment');
        $this->_isPkAutoIncrement = false;
    }

    /**
     * @param \Magento\Framework\Model\AbstractModel $object
     * @param $value
     * @param null $field
     * @return Casedata
     */
    public function loadForUpdate(\Magento\Framework\Model\AbstractModel $object, $value, $field = null)
    {
        $this->loadForUpdate = true;
        $return = parent::load($object, $value, $field);
        $this->loadForUpdate = false;

        return $return;
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
