<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Plugin\StripeIntegration\Payments\Helper;

use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\CasedataFactory;
use StripeIntegration\Payments\Helper\Order as StripeIntegrationOrder;

class Order
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
     * @var CasedataFactory
     */
    public $casedataFactory;

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
     * @param CasedataRepositoryInterface $casedataRepository
     * @param Logger $logger
     * @param CasedataFactory $casedataFactory
     * @param OrderResourceModel $orderResourceModel
     * @param OrderHelper $orderHelper
     */
    public function __construct(
        CasedataRepositoryInterface $casedataRepository,
        Logger $logger,
        CasedataFactory $casedataFactory,
        OrderResourceModel $orderResourceModel,
        OrderHelper $orderHelper
    ) {
        $this->casedataRepository = $casedataRepository;
        $this->logger = $logger;
        $this->casedataFactory = $casedataFactory;
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
            $case = $this->casedataRepository->getByOrderId($order->getId());

            if ($case->isEmpty()) {
                return;
            }

            if ($case->getData('magento_status') === Casedata::ASYNC_WAIT && empty($case->getData('code'))) {
                $case->setEntries('stripe_status', 'approved');
                $this->casedataRepository->save($case);
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
