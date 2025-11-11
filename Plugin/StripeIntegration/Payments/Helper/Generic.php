<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Plugin\StripeIntegration\Payments\Helper;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\CasedataFactory;
use StripeIntegration\Payments\Helper\Generic as StripeIntegrationGeneric;

/**
 * Observer for purchase event. Sends order data to Signifyd service
 */
class Generic
{
    /**
     * @var CasedataRepositoryInterface
     */
    public $casedataRepository;

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
     * Generic constructor.
     *
     * @param CasedataRepositoryInterface $casedataRepository
     * @param Logger $logger
     * @param OrderHelper $orderHelper
     * @param OrderResourceModel $orderResourceModel
     * @param CasedataFactory $casedataFactory
     */
    public function __construct(
        CasedataRepositoryInterface $casedataRepository,
        Logger $logger,
        OrderHelper $orderHelper,
        OrderResourceModel $orderResourceModel,
        CasedataFactory $casedataFactory,
    ) {
        $this->casedataRepository = $casedataRepository;
        $this->logger = $logger;
        $this->orderHelper = $orderHelper;
        $this->orderResourceModel = $orderResourceModel;
        $this->casedataFactory = $casedataFactory;
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
            $case = $this->casedataRepository->getByOrderId($order->getId());

            if ($case->isEmpty()) {
                return [$order, $refundInvoices, $refundOffline];
            }

            if ($case->getData('magento_status') === Casedata::ASYNC_WAIT && empty($case->getData('code'))) {
                $case->setEntries('async_action', 'delete');
                $this->casedataRepository->save($case);
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

            if (isset($order) && $order instanceof Order) {
                $context['entity'] = $order;
            }

            $this->logger->error($ex->getMessage(), $context);
        }

        return [$order, $refundInvoices, $refundOffline];
    }
}
