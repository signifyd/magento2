<?php

namespace Signifyd\Connect\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Psr\Log\LoggerInterface;
use Signifyd\Connect\Model\ResourceModel\Casedata;

/**
 * Observer for order grid building events. Appends Signifyd data
 */
class OrderGrid
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->_logger = $logger;
    }

    public function onSalesOrderGridCollectionLoadBefore(Observer $observer)
    {
        /** @var $collection \Magento\Sales\Model\ResourceModel\Order\Grid\Collection * */
        $collection = $observer->getData('order_grid_collection');
        Casedata::JoinWithOrder($collection);
    }
}
