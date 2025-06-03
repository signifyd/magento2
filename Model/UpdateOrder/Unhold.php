<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model\UpdateOrder;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;

/**
 * Defines link data for the comment field in the config page
 */
class Unhold
{
    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var OrderHelper
     */
    public $orderHelper;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var OrderResourceModel
     */
    public $orderResourceModel;

    /**
     * Unhold construct.
     *
     * @param ConfigHelper $configHelper
     * @param OrderHelper $orderHelper
     * @param Logger $logger
     * @param OrderResourceModel $orderResourceModel
     */
    public function __construct(
        ConfigHelper $configHelper,
        OrderHelper $orderHelper,
        Logger $logger,
        OrderResourceModel $orderResourceModel
    ) {
        $this->configHelper = $configHelper;
        $this->orderHelper = $orderHelper;
        $this->logger = $logger;
        $this->orderResourceModel = $orderResourceModel;
    }

    /**
     * Invoke method.
     *
     * @param Order $order
     * @param mixed $case
     * @param mixed $orderAction
     * @param bool $completeCase
     * @return mixed|true
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function __invoke($order, $case, $orderAction, $completeCase)
    {
        if ($order->canUnhold()) {
            $this->logger->debug('Unhold order action', ['entity' => $order]);

            try {
                $order->unhold();
                $this->orderResourceModel->save($order);

                $completeCase = true;

                $this->logger->debug("Signifyd: order status updated, {$orderAction["reason"]}", ['entity' => $order]);
                $this->orderHelper->addCommentToStatusHistory(
                    $order,
                    "Signifyd: order status updated, {$orderAction["reason"]}"
                );
            } catch (\Exception $e) {
                $this->logger->debug($e->__toString(), ['entity' => $order]);
                $case->setEntries('fail', 1);

                $this->orderHelper->addCommentToStatusHistory(
                    $order,
                    "Signifyd: order status cannot be updated, {$e->getMessage()}"
                );
                throw new LocalizedException(__($e->getMessage()));
            } catch (\Error $e) {
                $this->logger->debug($e->__toString(), ['entity' => $order]);
                $case->setEntries('fail', 1);

                $this->orderHelper->addCommentToStatusHistory(
                    $order,
                    "Signifyd: order status cannot be updated, {$e->getMessage()}"
                );
                throw new LocalizedException(__($e->getMessage()));
            }
        } else {
            $reason = $this->orderHelper->getCannotUnholdReason($order);

            $message = "Order {$order->getIncrementId()} ({$order->getState()} > {$order->getStatus()}) " .
                "can not be removed from hold because {$reason}. " .
                "Case status: {$case->getSignifydStatus()}";
            $this->logger->debug($message, ['entity' => $order]);
            $case->setEntries('fail', 1);

            $this->orderHelper->addCommentToStatusHistory(
                $order,
                "Signifyd: order status cannot be updated, {$reason}"
            );

            if ($reason == "order is not holded") {
                $completeCase = true;
            }
        }

        return $completeCase;
    }
}
