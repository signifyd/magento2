<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Plugin\StripeIntegration\Payments\Helper;

use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use StripeIntegration\Payments\Helper\Order as StripeIntegrationOrder;

class Order
{
    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    public $casedataResourceModel;

    /**
     * @var OrderResourceModel
     */
    public $orderResourceModel;

    /**
     * @var OrderHelper
     */
    public $orderHelper;

    /**
     * Order constructor.
     *
     * @param Logger $logger
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param OrderResourceModel $orderResourceModel
     * @param OrderHelper $orderHelper
     */
    public function __construct(
        Logger $logger,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        OrderResourceModel $orderResourceModel,
        OrderHelper $orderHelper
    ) {
        $this->logger = $logger;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->orderResourceModel = $orderResourceModel;
        $this->orderHelper = $orderHelper;
    }

    /**
     * Around on transaction method.
     *
     * @param StripeIntegrationOrder $subject
     * @param callable $proceed
     * @param Order $order
     * @param mixed $object
     * @param mixed $transactionId
     * @return void
     */
    public function aroundOnTransaction(
        StripeIntegrationOrder $subject,
        callable $proceed,
        $order,
        $object,
        $transactionId
    ) {
        try {
            $orderId = $order->getId();
            $case = $this->casedataFactory->create();
            $this->casedataResourceModel->load($case, $orderId, 'order_id');

            if ($case->isEmpty()) {
                return;
            }

            if ($case->getData('magento_status') === Casedata::ASYNC_WAIT && empty($case->getData('code'))) {
                $case->setEntries('stripe_status', 'approved');
                $this->casedataResourceModel->save($case);
            }

            $isHoldedBeforeStripeTransaction = $order->canUnhold();
            $proceed($order, $object, $transactionId);

            //Setting order to hold after stripe remove on transaction flow
            if ($isHoldedBeforeStripeTransaction && $order->canHold()) {
                $order->hold();
                $this->orderResourceModel->save($order);
                $this->logger->info(
                    "Hold order {$order->getIncrementId()} after stripe remove",
                    ['entity' => $case]
                );

                $this->orderHelper->addCommentToStatusHistory(
                    $order,
                    "Signifyd: hold order after stripe remove"
                );
            }
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
