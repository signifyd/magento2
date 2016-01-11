<?php

namespace Signifyd\Connect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Psr\Log\LoggerInterface;
use Signifyd\Connect\Model\ResourceModel\Casedata;

/**
 * Observer for order grid building events. Appends Signifyd data
 */
class OrderGrid implements ObserverInterface
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

    public function execute(Observer $observer)
    {
        /** @var $collection \Magento\Sales\Model\ResourceModel\Order\Grid\Collection * */
        $collection = $observer->getData('order_grid_collection');
        Casedata::JoinWithOrder($collection);
    }
}
