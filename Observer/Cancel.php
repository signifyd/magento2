<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Api\Core\Client;

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
     * @var \Signifyd\Connect\Helper\ConfigHelper
     */
    protected $configHelper;

    /**
     * @var Client
     */
    protected $client;

    /**
     * Cancel constructor.
     * @param Logger $logger
     * @param \Signifyd\Connect\Helper\ConfigHelper $configHelper
     * @param Client $client
     */
    public function __construct(
        Logger $logger,
        \Signifyd\Connect\Helper\ConfigHelper $configHelper,
        Client $client
    ) {
        $this->logger = $logger;
        $this->configHelper = $configHelper;
        $this->client = $client;
    }

    public function execute(Observer $observer)
    {
        try {
            /** @var Order $order */
            $order = $observer->getEvent()->getOrder();

            if ($order instanceof Order == false) {
                /** @var \Magento\Sales\Model\Order\Payment $payment */
                $payment = $observer->getEvent()->getPayment();

                if ($payment instanceof \Magento\Sales\Model\Order\Payment) {
                    $order = $payment->getOrder();
                }

                if ($order instanceof Order == false) {
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

            $this->client->cancelCaseOnSignifyd($order);
        } catch (\Exception $ex) {
            $context = [];

            if (isset($order) && $order instanceof Order) {
                $context['entity'] = $order;
            }

            $this->logger->error($ex->getMessage(), $context);
        } catch (\Error $ex) {
            $context = [];

            if (isset($order) && $order instanceof Order) {
                $context['entity'] = $order;
            }

            $this->logger->error($ex->getMessage(), $context);
        }
    }
}
