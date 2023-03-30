<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model\Casedata\UpdateOrder;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;

/**
 * Defines link data for the comment field in the config page
 */
class Cancel
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
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

                $this->orderHelper->addCommentToStatusHistory(
                    $order,
                    "Signifyd: order canceled, {$orderAction["reason"]}"
                );
            } catch (\Exception $e) {
                $this->logger->debug($e->__toString(), ['entity' => $order]);
                $case->setEntries('fail', 1);
                $case->holdOrder($order);
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

        $order = $case->getOrder(true);

        if ($orderAction['action'] === false && $order->canHold()) {
            $order->hold();
            $this->orderResourceModel->save($order);
        }

        return $completeCase;
    }
}