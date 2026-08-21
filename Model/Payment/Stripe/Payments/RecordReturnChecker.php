<?php

namespace Signifyd\Connect\Model\Payment\Stripe\Payments;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Registry;
use Signifyd\Connect\Model\Payment\Base\RecordReturnChecker as BaseRecordReturnChecker;

class RecordReturnChecker extends BaseRecordReturnChecker
{
    /**
     * Registry key holding the status of the Stripe dispute being processed, if any.
     */
    public const DISPUTE_STATUS_REGISTRY_KEY = 'signifyd_stripe_dispute_status';

    /**
     * Stripe dispute statuses that make the Stripe module create a credit memo on its own.
     */
    public const CHARGEBACK_DISPUTE_STATUSES = ['lost'];

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var Registry
     */
    public $registry;

    /**
     * RecordReturnChecker construct.
     *
     * @param Logger $logger
     * @param Registry $registry
     */
    public function __construct(
        Logger $logger,
        Registry $registry
    ) {
        $this->logger = $logger;
        $this->registry = $registry;
    }

    /**
     * Invoke method.
     *
     * Suppresses the Record Return when the credit memo is being created during the processing
     * of a Stripe lost dispute webhook event, instead of a manual/refund flow.
     *
     * @param Order $order
     * @param Creditmemo $creditmemo
     * @return bool
     */
    public function __invoke(Order $order, Creditmemo $creditmemo)
    {
        $disputeStatus = $this->registry->getData(self::DISPUTE_STATUS_REGISTRY_KEY);
        $isDisputeDriven = in_array($disputeStatus, self::CHARGEBACK_DISPUTE_STATUSES, true);

        if ($isDisputeDriven) {
            $this->logger->info(
                'Record Return skipped: credit memo originated from Stripe dispute for order '
                    . $order->getIncrementId(),
                ['entity' => $order]
            );
        }

        return $isDisputeDriven;
    }
}
