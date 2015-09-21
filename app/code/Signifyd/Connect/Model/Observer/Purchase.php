<?php

namespace Signifyd\Connect\Model\Observer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Psr\Log\LoggerInterface;
use \Magento\Framework\ObjectManagerInterface;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Helper\LogHelper;

/**
 * Observer for purchase event. Sends order data to Signifyd service
 */
class Purchase
{
    /**
     * @var \Signifyd\Connect\Helper\LogHelper
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
        try {
            $this->_logger = new LogHelper($logger, $scopeConfig);
            $this->_objectManager = $objectManager;
            $this->_helper = new PurchaseHelper($objectManager, $logger, $scopeConfig);
        }
        catch(\Exception $ex)
        {
            $this->_logger->error($ex->getMessage());
        }
    }

    public function sendOrderToSignifyd(Observer $observer)
    {
        try {
            /** @var $order Order */
            $order = $observer->getEvent()->getOrder();
            $this->_logger->debug("Order received");
            $orderData = $this->_helper->processOrderData($order);
            $this->_logger->debug("Order data made received");

            // Inspect data
            $items = $order->getAllItems();
            $this->_logger->debug("Items received");
            foreach($items as $item)
            {
                $this->_logger->debug($item->convertToJson());
            }
            $this->_logger->debug("Items done");
            $this->_logger->debug(json_encode($orderData));
            $this->_logger->debug("Order data done");
            $this->_logger->debug($order->convertToJson());
            $this->_logger->debug("Order json done");

            // Add order to database
            $this->_helper->createNewCase($order);
            $this->_logger->debug("New case done");

            // Post case to signifyd service
            $this->_helper->postCaseToSignifyd($orderData);
            $this->_logger->debug("Post done");
        }
        catch(\Exception $ex)
        {
            $this->_logger->error($ex->getMessage());
        }
    }
}
