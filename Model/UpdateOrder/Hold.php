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
class Hold
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
     * Hold construct.
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
        if ($order->canHold()) {
            try {
                $order->hold();
                $this->orderResourceModel->save($order);
                $completeCase = true;

                $this->logger->debug("Signifyd: {$orderAction["reason"]}", ['entity' => $order]);
                $this->orderHelper->addCommentToStatusHistory(
                    $order,
                    "Signifyd: {$orderAction["reason"]}"
                );
            } catch (\Exception $e) {
                $this->logger->debug($e->__toString(), ['entity' => $order]);
                $case->setEntries('fail', 1);

                $message = "Signifyd: order cannot be updated to on hold, {$e->getMessage()}";
                $this->orderHelper->addCommentToStatusHistory($order, $message);
                throw new LocalizedException(__($e->getMessage()));
            } catch (\Error $e) {
                $this->logger->debug($e->__toString(), ['entity' => $order]);
                $case->setEntries('fail', 1);

                $message = "Signifyd: order cannot be updated to on hold, {$e->getMessage()}";
                $this->orderHelper->addCommentToStatusHistory($order, $message);
                throw new LocalizedException(__($e->getMessage()));
            }
        } else {
            $reason = $this->orderHelper->getCannotHoldReason($order);
            $message = "Order {$order->getIncrementId()} can not be held because {$reason}";
            $this->logger->debug($message, ['entity' => $order]);
            $case->setEntries('fail', 1);
            $this->orderHelper->addCommentToStatusHistory(
                $order,
                "Signifyd: order cannot be updated to on hold, {$reason}"
            );

            if ($order->getState() == Order::STATE_HOLDED) {
                $completeCase = true;
            }
        }

        return $completeCase;
    }
}
