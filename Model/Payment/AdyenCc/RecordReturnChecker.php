<?php

namespace Signifyd\Connect\Model\Payment\AdyenCc;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Registry;
use Signifyd\Connect\Model\Payment\Base\RecordReturnChecker as BaseRecordReturnChecker;

class RecordReturnChecker extends BaseRecordReturnChecker
{
    /**
     * Adyen notification event codes that make the Adyen module create a credit memo on its own.
     */
    public const CHARGEBACK_EVENT_CODES = ['CHARGEBACK', 'SECOND_CHARGEBACK'];

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
     * of an Adyen chargeback webhook notification, instead of a manual/refund flow.
     *
     * @param Order $order
     * @param Creditmemo $creditmemo
     * @return bool
     */
    public function __invoke(Order $order, Creditmemo $creditmemo)
    {
        $eventCode = $this->registry->getData('signifyd_notification_event_code');
        $isChargebackDriven = in_array($eventCode, self::CHARGEBACK_EVENT_CODES, true);

        if ($isChargebackDriven) {
            $this->logger->info(
                'Record Return skipped: credit memo originated from Adyen chargeback for order '
                    . $order->getIncrementId(),
                ['entity' => $order]
            );
        }

        return $isChargebackDriven;
    }
}
