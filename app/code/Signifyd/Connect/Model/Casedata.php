<?php

/**
 * ORM model declaration for case data
 */
class Casedata extends \Magento\Framework\Model\Resource\Db\AbstractDb
{
    /**
     * Initialize connection and define main table
     */
    protected function _construct()
    {
        $this->_init('signifyd_connect_case', 'order_increment');
        $this->_isPkAutoIncrement = false;
    }
}
