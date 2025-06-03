<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model\UpdateOrder;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;

/**
 * Defines link data for the comment field in the config page
 */
class Cancel
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
     * @var OrderFactory
     */
    public $orderFactory;

    /**
     * @var SignifydOrderResourceModel
     */
    public $signifydOrderResourceModel;

    /**
     * Cancel construct.
     *
     * @param ConfigHelper $configHelper
     * @param OrderHelper $orderHelper
     * @param Logger $logger
     * @param OrderResourceModel $orderResourceModel
     * @param OrderFactory $orderFactory
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     */
    public function __construct(
        ConfigHelper $configHelper,
        OrderHelper $orderHelper,
        Logger $logger,
        OrderResourceModel $orderResourceModel,
        OrderFactory $orderFactory,
        SignifydOrderResourceModel $signifydOrderResourceModel
    ) {
        $this->configHelper = $configHelper;
        $this->orderHelper = $orderHelper;
        $this->logger = $logger;
        $this->orderResourceModel = $orderResourceModel;
        $this->orderFactory = $orderFactory;
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;
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
            $order = $order->unhold();
            $this->orderResourceModel->save($order);
        }

        if ($order->canCancel()) {
            try {
                $order->cancel();
                $this->orderResourceModel->save($order);
                $completeCase = true;

                $this->logger->debug("Signifyd: order canceled, {$orderAction["reason"]}", ['entity' => $order]);
                $this->orderHelper->addCommentToStatusHistory(
                    $order,
                    "Signifyd: order canceled, {$orderAction["reason"]}"
                );
            } catch (\Exception $e) {
                $this->logger->debug($e->__toString(), ['entity' => $order]);
                $case->setEntries('fail', 1);

                if ($order->canHold()) {
                    $order->hold();
                    $this->signifydOrderResourceModel->save($order);
                }

                $orderAction['action'] = false;

                $this->orderHelper->addCommentToStatusHistory(
                    $order,
                    "Signifyd: order cannot be canceled, {$e->getMessage()}"
                );
                throw new LocalizedException(__($e->getMessage()));
            } catch (\Error $e) {
                $this->logger->debug($e->__toString(), ['entity' => $order]);
                $case->setEntries('fail', 1);

                if ($order->canHold()) {
                    $order->hold();
                    $this->signifydOrderResourceModel->save($order);
                }

                $orderAction['action'] = false;

                $this->orderHelper->addCommentToStatusHistory(
                    $order,
                    "Signifyd: order cannot be canceled, {$e->getMessage()}"
                );
                throw new LocalizedException(__($e->getMessage()));
            }
        } else {
            $reason = $this->orderHelper->getCannotCancelReason($order);
            $message = "Order {$order->getIncrementId()} cannot be canceled because {$reason}";
            $this->logger->debug($message, ['entity' => $order]);
            $case->setEntries('fail', 1);
            $orderAction['action'] = false;
            $this->orderHelper->addCommentToStatusHistory(
                $order,
                "Signifyd: order cannot be canceled, {$reason}"
            );

            if ($reason == "all order items are invoiced") {
                $completeCase = true;
            }
        }

        $order = $this->orderFactory->create();
        $this->signifydOrderResourceModel->load($order, $case->getData('order_id'));

        if ($orderAction['action'] === false && $order->canHold()) {
            $order->hold();
            $this->orderResourceModel->save($order);
        }

        return $completeCase;
    }
}
