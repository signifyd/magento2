<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Logger\Logger;

/**
 * Observer for purchase event. Sends order data to Signifyd service
 */
class Cancel implements ObserverInterface
{
    /**
     * @var \Signifyd\Connect\Logger\Logger
     */
    protected $logger;

    /**
     * @var \Signifyd\Connect\Helper\PurchaseHelper
     */
    protected $purchaseHelper;

    /**
     * @var \Signifyd\Connect\Helper\ConfigHelper
     */
    protected $configHelper;

    /**
     * Cancel constructor.
     * @param Logger $logger
     * @param PurchaseHelper $purchaseHelper
     * @param \Signifyd\Connect\Helper\ConfigHelper $configHelper
     */
    public function __construct(
        Logger $logger,
        PurchaseHelper $purchaseHelper,
        \Signifyd\Connect\Helper\ConfigHelper $configHelper
    ) {
        $this->logger = $logger;
        $this->purchaseHelper = $purchaseHelper;
        $this->configHelper = $configHelper;
    }

    public function execute(Observer $observer)
    {
        try {
            /** @var $order Order */
            $order = $observer->getEvent()->getOrder();

            if ($order instanceof Order == false) {
                /** @var \Magento\Sales\Model\Order\Payment $payment */
                $payment = $observer->getEvent()->getPayment();

                if ($payment instanceof \Magento\Sales\Model\Order\Payment) {
                    $order = $payment->getOrder();
                }

                if ($order instanceof Order == false) {
                    /** @var \Magento\Sales\Model\Creditmemo $creditmemo */
                    $creditmemo = $observer->getEvent()->getCreditmemo();

                    if ($creditmemo instanceof \Magento\Sales\Model\Order\Creditmemo) {
                        $order = $creditmemo->getOrder();
                    }
                }
            }

            if ($order instanceof Order == false) {
                return;
            }

            if ($this->configHelper->isEnabled($order) == false) {
                return;
            }

            $this->purchaseHelper->cancelCaseOnSignifyd($order);
        } catch (\Exception $ex) {
            $context = [];

            if (isset($order) && $order instanceof Order) {
                $context['entity'] = $order;
            }

            $this->logger->error($ex->getMessage(), $context);
        }
    }
}
