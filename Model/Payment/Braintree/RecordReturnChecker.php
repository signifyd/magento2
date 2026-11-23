<?php

namespace Signifyd\Connect\Model\Payment\Braintree;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Payment\Base\RecordReturnChecker as BaseRecordReturnChecker;

class RecordReturnChecker extends BaseRecordReturnChecker
{
    /**
     * Order attribute the Braintree module keeps the status of the dispute on.
     */
    public const DISPUTE_STATUS_ATTRIBUTE = 'dispute_status';

    /**
     * Braintree dispute statuses where the dispute itself took the money, so the chargeback was
     * already reported by Signifyd\Connect\Plugin\Braintree\Controller\Webhook\FraudProtection
     * and a credit memo created afterwards is only bookkeeping. A won dispute is left out, as the
     * merchant kept the money and a refund issued later is a return of its own.
     */
    public const CHARGEBACK_DISPUTE_STATUSES = [
        'opened',
        'disputed',
        'accepted',
        'auto accepted',
        'lost',
        'expired',
    ];

    /**
     * @var Logger
     */
    public $logger;

    /**
     * RecordReturnChecker construct.
     *
     * @param Logger $logger
     */
    public function __construct(
        Logger $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Invoke method.
     *
     * Suppresses the Record Return of a disputed order. Braintree creates no credit memo for a
     * dispute, as done for Adyen and Stripe, so the credit memo reaching here is a manual one
     * created after the chargeback was reported, and the return it would record never happened.
     *
     * @param Order $order
     * @param Creditmemo $creditmemo
     * @return bool
     */
    public function __invoke(Order $order, Creditmemo $creditmemo)
    {
        $disputeStatus = strtolower(trim((string) $order->getData(self::DISPUTE_STATUS_ATTRIBUTE)));
        $isDisputeDriven = in_array($disputeStatus, self::CHARGEBACK_DISPUTE_STATUSES, true);

        if ($isDisputeDriven) {
            $this->logger->info(
                'Record Return skipped: order ' . $order->getIncrementId()
                    . ' has a Braintree dispute with status ' . $disputeStatus,
                ['entity' => $order]
            );
        }

        return $isDisputeDriven;
    }
}
