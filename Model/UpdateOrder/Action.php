<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model\UpdateOrder;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;

/**
 * Defines link data for the comment field in the config page
 */
class Action
{
    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var OrderFactory
     */
    public $orderFactory;

    /**
     * @var SignifydOrderResourceModel
     */
    public $signifydOrderResourceModel;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * Action construct.
     *
     * @param Logger $logger
     * @param OrderFactory $orderFactory
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        Logger $logger,
        OrderFactory $orderFactory,
        SignifydOrderResourceModel $signifydOrderResourceModel,
        ConfigHelper $configHelper
    ) {
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;
        $this->configHelper = $configHelper;
    }

    /**
     * Handle guarantee change method.
     *
     * @param mixed $case
     * @return array|string[]
     */
    public function handleGuaranteeChange($case)
    {
        $requestGuarantee = $case->getOrigData('guarantee');
        $caseGuarantee = $case->getData('guarantee');
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->orderFactory->create();
        $this->signifydOrderResourceModel->load($order, $case->getData('order_id'));

        // Reviewed Cases
        if (($requestGuarantee == 'REJECT' || $requestGuarantee == 'DECLINED') &&
            $requestGuarantee != $caseGuarantee &&
            $order->getState() === Order::STATE_CANCELED
        ) {
            return ["action" => 'nothing', "reason" => 'declined guarantees reviewed to approved'];
        }

        switch ($case->getGuarantee()) {
            case "REJECT":
            case "DECLINED":
                $result = ["action" => $this->getNegativeAction($case), "reason" => "guarantee declined"];
                break;

            case 'ACCEPT':
            case "APPROVED":
                $result = ["action" => $this->getPositiveAction($case), "reason" => "guarantee approved"];
                break;

            case 'PENDING':
                $result = ["action" => 'wait', "reason" => 'case in manual review'];
                break;

            default:
                $result = ["action" => '', "reason" => ''];
        }

        $this->logger->debug("Action for {$case->getOrderIncrement()}: {$result['action']}", ['entity' => $case]);

        return $result;
    }

    /**
     * Get positive action method.
     *
     * @param mixed $case
     * @return mixed|string
     */
    public function getPositiveAction($case)
    {
        if ($this->isHoldReleased($case)) {
            return 'nothing';
        } else {
            return $this->configHelper->getConfigData('signifyd/advanced/guarantee_positive_action', $case);
        }
    }

    /**
     * Get negative action
     *
     * @param mixed $case
     * @return mixed|string
     */
    public function getNegativeAction($case)
    {
        if ($this->isHoldReleased($case)) {
            return 'nothing';
        } else {
            return $this->configHelper->getConfigData('signifyd/advanced/guarantee_negative_action', $case);
        }
    }

    /**
     * Is hold released method.
     *
     * @param mixed $case
     * @return bool
     */
    public function isHoldReleased($case)
    {
        $holdReleased = $case->getEntries('hold_released');
        return (($holdReleased == 1) ? true : false);
    }
}
