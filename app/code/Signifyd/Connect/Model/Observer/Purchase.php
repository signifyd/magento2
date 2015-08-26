<?php

namespace Signifyd\Connect\Model\Observer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Psr\Log\LoggerInterface;
use \Magento\Framework\ObjectManagerInterface;
use Signifyd\Connect\Helper\PurchaseHelper;

/**
 * Observer for purchase event. Sends order data to Signifyd service
 */
class Purchase
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;


    /**
     * @var \Signifyd\Connect\Helper\PurchaseHelper
     */
    protected $_helper;

    public function __construct(
        ObjectManagerInterface $objectManager,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->_logger = $logger;
        $this->_objectManager = $objectManager;
        $this->_helper = new PurchaseHelper($objectManager, $logger, $scopeConfig);
    }

    public function sendOrderToSignifyd(Observer $observer)
    {
        /** @var $order Order */
        $order = $observer->getEvent()->getOrder();
        $this->_logger->info("Order received");
        $orderData = $this->_helper->processOrderData($order);

        // Inspect data
        $items = $order->getAllItems();
        foreach($items as $item)
        {
            $this->_logger->info($item->convertToJson());
        }
        $this->_logger->info(json_encode($orderData));
        $this->_logger->info($order->convertToJson());

        // Add order to database
        $this->_helper->createNewCase($order);

        // Post case to signifyd service
        $this->_helper->postCaseToSignifyd($orderData);
    }
}
