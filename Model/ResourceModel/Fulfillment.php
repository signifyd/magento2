<?php

namespace Signifyd\Connect\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * ORM model declaration for case data
 */
class Fulfillment extends AbstractDb
{
    protected $_isPkAutoIncrement = false;

    /**
     * Initialize connection and define main table
     */
    protected function _construct()
    {
        $this->_init('signifyd_connect_fulfillment', 'id');
    }
}
