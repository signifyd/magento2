<?php

namespace Signifyd\Connect\Model\Payment\AdyenCc;

use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Payment\Base\RecordReturnChecker as BaseRecordReturnChecker;

class RecordReturnChecker extends BaseRecordReturnChecker
{
    /**
     * Adyen notification event codes that make the Adyen module create a credit memo on its own.
     */
    public const CHARGEBACK_EVENT_CODES = ['CHARGEBACK', 'SECOND_CHARGEBACK'];

    /**
     * Adyen links an unlinked adyen_creditmemo row to the Magento credit memo that matches its amount
     * (Helper/Creditmemo::linkAndUpdateAdyenCreditmemos) within the same request/cron run that created it.
     * Bounding the match to this age keeps an old, never-linked row (e.g. an aborted chargeback flow) from
     * being matched by coincidence against a later, unrelated credit memo of the same amount.
     */
    public const UNLINKED_CREDITMEMO_MAX_AGE_SECONDS = 1800;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var ObjectManagerInterface
     */
    public $objectManager;

    /**
     * RecordReturnChecker construct.
     *
     * @param Logger $logger
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        Logger $logger,
        ObjectManagerInterface $objectManager
    ) {
        $this->logger = $logger;
        $this->objectManager = $objectManager;
    }

    /**
     * Invoke method.
     *
     * Suppresses the Record Return when the credit memo behind the order was created by the Adyen
     * module's own chargeback webhook handling, instead of a manual/refund flow.
     *
     * @param Order $order
     * @param Creditmemo $creditmemo
     * @return bool
     */
    public function __invoke(Order $order, Creditmemo $creditmemo)
    {
        $pspReference = $this->findChargebackPspReference($order, $creditmemo);

        if (empty($pspReference)) {
            return false;
        }

        $notificationCollectionFactory = $this->createIfExists(
            'Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory'
        );

        if ($notificationCollectionFactory === null) {
            return false;
        }

        $collection = $notificationCollectionFactory->create();
        $collection->addFieldToFilter('pspreference', $pspReference);
        $collection->addFieldToFilter('event_code', ['in' => self::CHARGEBACK_EVENT_CODES]);

        $isChargebackDriven = $collection->getSize() > 0;

        if ($isChargebackDriven) {
            $message = 'Record Return skipped: credit memo originated from Adyen chargeback for order '
                . $order->getIncrementId();
            $this->logger->info($message, ['entity' => $order]);
        }

        return $isChargebackDriven;
    }

    /**
     * Finds the pspReference of the Adyen modification/webhook that produced this credit memo.
     *
     * Signifyd's observer runs on sales_order_creditmemo_refund, which fires before the credit memo is
     * saved to the database -- so Adyen's own adyen_creditmemo.creditmemo_id link (set later by
     * Adyen\Payment\Observer\CreditmemoObserver, on sales_order_creditmemo_save_after) does not exist yet.
     * This mirrors Adyen's own linking algorithm (Helper/Creditmemo::linkAndUpdateAdyenCreditmemos): match
     * an unlinked adyen_creditmemo row for this order's payment by amount, falling back to an exact
     * creditmemo_id match in case this ever runs after the link was already made.
     *
     * @param Order $order
     * @param Creditmemo $creditmemo
     * @return string|null
     */
    private function findChargebackPspReference(Order $order, Creditmemo $creditmemo)
    {
        $orderPaymentCollectionFactory = $this->createIfExists(
            'Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory'
        );
        $creditmemoCollectionFactory = $this->createIfExists(
            'Adyen\Payment\Model\ResourceModel\Creditmemo\CollectionFactory'
        );

        if ($orderPaymentCollectionFactory === null || $creditmemoCollectionFactory === null) {
            return null;
        }

        $adyenOrderPaymentIds = $orderPaymentCollectionFactory->create()
            ->addFieldToFilter('payment_id', $order->getPayment()->getEntityId())
            ->getAllIds();

        if (empty($adyenOrderPaymentIds)) {
            return null;
        }

        $adyenCreditmemos = $creditmemoCollectionFactory->create()
            ->addFieldToFilter('adyen_order_payment_id', ['in' => $adyenOrderPaymentIds]);

        foreach ($adyenCreditmemos as $adyenCreditmemo) {
            $linkedCreditmemoId = $adyenCreditmemo->getData('creditmemo_id');

            if ($linkedCreditmemoId !== null) {
                if ($linkedCreditmemoId == $creditmemo->getEntityId()) {
                    return $adyenCreditmemo->getData('pspreference');
                }

                continue;
            }

            if ($adyenCreditmemo->getData('amount') == $creditmemo->getGrandTotal()) {
                $age = time() - strtotime($adyenCreditmemo->getData('created_at'));

                if ($age <= self::UNLINKED_CREDITMEMO_MAX_AGE_SECONDS) {
                    return $adyenCreditmemo->getData('pspreference');
                }
            }
        }

        return null;
    }

    /**
     * Safely creates an instance of an optional third-party class, returning null when the owning
     * module (e.g. Adyen_Payment) is not installed, instead of failing DI compilation for stores that
     * don't have it.
     *
     * @param string $class
     * @return object|null
     */
    private function createIfExists($class)
    {
        return class_exists($class) ? $this->objectManager->create($class) : null;
    }
}
