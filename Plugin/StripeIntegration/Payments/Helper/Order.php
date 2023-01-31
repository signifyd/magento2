<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Plugin\StripeIntegration\Payments\Helper;

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
    protected $logger;

    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * Cancel constructor.
     * @param Logger $logger
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     */
    public function __construct(
        Logger $logger,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel
    ) {
        $this->logger = $logger;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
    }

    public function beforeOnTransaction(StripeIntegrationOrder $subject, $order, $object, $transactionId) {
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
