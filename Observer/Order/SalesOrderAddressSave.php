<?php

namespace Signifyd\Connect\Observer\Order;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Models\PaymentUpdateFactory;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;

class SalesOrderAddressSave implements ObserverInterface
{
    /**
     * @var PaymentUpdateFactory
     */
    protected $paymentUpdateFactory;

    /**
     * @var PurchaseHelper
     */
    protected $purchaseHelper;

    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param PaymentUpdateFactory $paymentUpdateFactory
     * @param PurchaseHelper $purchaseHelper
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param ConfigHelper $configHelper
     * @param JsonSerializer $jsonSerializer
     * @param Logger $logger
     */
    public function __construct(
        PaymentUpdateFactory $paymentUpdateFactory,
        PurchaseHelper $purchaseHelper,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        ConfigHelper $configHelper,
        JsonSerializer $jsonSerializer,
        Logger $logger
    ) {
        $this->paymentUpdateFactory = $paymentUpdateFactory;
        $this->purchaseHelper = $purchaseHelper;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->configHelper = $configHelper;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        try {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $observer->getEvent()->getAddress()->getOrder();

            if (!is_object($order)) {
                return;
            }

            if ($order->hasShipments()) {
                return;
            }

            if ($this->configHelper->isEnabled($order) == false) {
                return;
            }

            $orderId = $order->getId();
            $case = $this->casedataFactory->create();
            $this->casedataResourceModel->load($case, $orderId, 'order_id');

            if ($case->isEmpty()) {
                return;
            }

            $this->logger->info("Send case update for order {$order->getIncrementId()}");

            $shipments = $this->purchaseHelper->makeShipments($order);
            $shipmentsJson = $this->jsonSerializer->serialize($shipments);
            $newHash = sha1($shipmentsJson);
            $currentHash = $case->getEntries('hash');

            if ($newHash == $currentHash) {
                $this->logger->info("No data changes, will not update order {$order->getIncrementId()}");
                return;
            }

            $rerout = [];
            $rerout['orderId'] = $order->getIncrementId();
            $rerout['device'] = $this->purchaseHelper->makeDevice($order->getQuoteId(), $order->getStoreId());
            $rerout['shipments'] = $shipments;
            $updateResponse = $this->purchaseHelper->createReroute($rerout, $order);

            $this->logger->info("Case updated for order {$order->getIncrementId()}");
            $this->logger->info($this->jsonSerializer->serialize($updateResponse));

            $case->setEntries('hash', $newHash);
            $case->updateCase($updateResponse);

            if ($case->getOrigData('signifyd_status') !== $case->getData('signifyd_status')) {
                $case->setStatus(\Signifyd\Connect\Model\Casedata::IN_REVIEW_STATUS);
                $case->updateOrder();
            }

            $this->casedataResourceModel->save($case);
        } catch (\Exception $e) {
            $this->logger->info('Failed to update case');
        }
    }
}
