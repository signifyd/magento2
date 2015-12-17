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
     * @var \Signifyd\Connect\Helper\PurchaseHelper
     */
    protected $_helper;

    public function __construct(
        ObjectManagerInterface $objectManager,
        LogHelper $logger,
        ScopeConfigInterface $scopeConfig,
        PurchaseHelper $helper
    ) {
        $this->_logger = $logger;
        $this->_helper = $helper;
    }

    public function sendOrderToSignifyd(Observer $observer)
    {
        try {
            /** @var $order Order */
            $order = $observer->getEvent()->getOrder();

            // Check if case already exists for this order
            if ($this->_helper->doesCaseExist($order)) {
                return;
            }

            $orderData = $this->_helper->processOrderData($order);

            // Add order to database
            $this->_helper->createNewCase($order);

            // Post case to signifyd service
            $this->_helper->postCaseToSignifyd($orderData);
        } catch (\Exception $ex) {
            $this->_logger->error($ex->getMessage());
        }
    }
}
