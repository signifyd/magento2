<?php

namespace Signifyd\Connect\Model\ResourceModel\Fulfillment;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \Signifyd\Connect\Model\Fulfillment::class,
            \Signifyd\Connect\Model\ResourceModel\Fulfillment::class
        );
    }
}
