<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Plugin\StripeIntegration\Payments\Helper;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use StripeIntegration\Payments\Helper\Generic as StripeIntegrationGeneric;

/**
 * Observer for purchase event. Sends order data to Signifyd service
 */
class Generic
{
    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var OrderHelper
     */
    public $orderHelper;

    /**
     * @var OrderResourceModel
     */
    public $orderResourceModel;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    public $casedataResourceModel;

    /**
     * Generic constructor.
     *
     * @param Logger $logger
     * @param OrderHelper $orderHelper
     * @param OrderResourceModel $orderResourceModel
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     */
    public function __construct(
        Logger $logger,
        OrderHelper $orderHelper,
        OrderResourceModel $orderResourceModel,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel
    ) {
        $this->logger = $logger;
        $this->orderHelper = $orderHelper;
        $this->orderResourceModel = $orderResourceModel;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
    }

    /**
     * Before cancel or close order method.
     *
     * @param StripeIntegrationGeneric $subject
     * @param Order $order
     * @param mixed $refundInvoices
     * @param mixed $refundOffline
     * @return array
     */
    public function beforeCancelOrCloseOrder(
        StripeIntegrationGeneric $subject,
        $order,
        $refundInvoices = null,
        $refundOffline = null
    ) {
        try {
            $orderId = $order->getId();
            $case = $this->casedataFactory->create();
            $this->casedataResourceModel->load($case, $orderId, 'order_id');

            if ($case->isEmpty()) {
                return [$order, $refundInvoices, $refundOffline];
            }

            if ($case->getData('magento_status') === Casedata::ASYNC_WAIT && empty($case->getData('code'))) {
                $case->setEntries('async_action', 'delete');
                $this->casedataResourceModel->save($case);
            }

            if ($order->canUnhold()) {
                try {
                    $order->unhold();
                    $this->orderResourceModel->save($order);
                    $this->logger->info(
                        "Unhold order {$order->getIncrementId()} before stripe tries to cancel or close",
                        ['entity' => $order]
                    );

                    $this->orderHelper->addCommentToStatusHistory(
                        $order,
                        "Signifyd: unhold order before stripe tries to cancel or close"
                    );
                } catch (\Exception $e) {
                    $this->logger->debug($e->__toString(), ['entity' => $order]);

                    $this->orderHelper->addCommentToStatusHistory(
                        $order,
                        "Signifyd: order cannot be unholded, {$e->getMessage()}"
                    );
                }
            }
        } catch (\Exception $ex) {
            $context = [];

            if ($order instanceof Order) {
                $context['entity'] = $order;
            }

            $this->logger->error($ex->getMessage(), $context);
        }

        return [$order, $refundInvoices, $refundOffline];
    }
}
