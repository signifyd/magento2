<?php

use Signifyd\Connect\Helper\LogHelper;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var \Signifyd\Connect\Helper\LogHelper
     */
    protected $_logger;

    public function __construct(
        LogHelper $logger
    ) {
        $this->_logger = $logger;
    }

    /**
     * Model initialization\
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Signifyd\Connect\Model\Casedata', 'Signifyd\Connect\Model\ResourceModel\Casedata');
    }
}
