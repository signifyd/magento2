<?php

namespace Signifyd\Connect\Observer\Order;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Casedata\UpdateCaseFactory;
use Signifyd\Models\PaymentUpdateFactory;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\UpdateOrderFactory;
use Signifyd\Connect\Model\Api\ShipmentsFactory;
use Signifyd\Connect\Model\Api\DeviceFactory;
use Signifyd\Connect\Model\Api\Core\Client;

class SalesOrderAddressSave implements ObserverInterface
{
    /**
     * @var PaymentUpdateFactory
     */
    protected $paymentUpdateFactory;

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
     * @var UpdateCaseFactory
     */
    protected $updateCaseFactory;

    /**
     * @var UpdateOrderFactory
     */
    protected $updateOrderFactory;

    /**
     * @var ShipmentsFactory
     */
    protected $shipmentsFactory;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var DeviceFactory
     */
    protected $deviceFactory;

    /**
     * @param PaymentUpdateFactory $paymentUpdateFactory
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param ConfigHelper $configHelper
     * @param JsonSerializer $jsonSerializer
     * @param Logger $logger
     * @param UpdateCaseFactory $updateCaseFactory
     * @param UpdateOrderFactory $updateOrderFactory
     * @param ShipmentsFactory $shipmentsFactory
     * @param Client $client
     * @param DeviceFactory $deviceFactory
     */
    public function __construct(
        PaymentUpdateFactory $paymentUpdateFactory,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        ConfigHelper $configHelper,
        JsonSerializer $jsonSerializer,
        Logger $logger,
        UpdateCaseFactory $updateCaseFactory,
        UpdateOrderFactory $updateOrderFactory,
        ShipmentsFactory $shipmentsFactory,
        Client $client,
        DeviceFactory $deviceFactory
    ) {
        $this->paymentUpdateFactory = $paymentUpdateFactory;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->configHelper = $configHelper;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
        $this->updateCaseFactory = $updateCaseFactory;
        $this->updateOrderFactory = $updateOrderFactory;
        $this->shipmentsFactory = $shipmentsFactory;
        $this->client = $client;
        $this->deviceFactory = $deviceFactory;
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

            $makeShipments = $this->shipmentsFactory->create();
            $shipments = $makeShipments($order);
            $recipient = $shipments[0]['destination'];
            $recipientJson = $this->jsonSerializer->serialize($recipient);
            $newHashToValidateReroute = sha1($recipientJson);
            $currentHashToValidateReroute = $case->getEntries('hash');

            if ($newHashToValidateReroute == $currentHashToValidateReroute) {
                $this->logger->info("No data changes, will not update order {$order->getIncrementId()}");
                return;
            }

            $device = $this->deviceFactory->create();
            $rerout = [];
            $rerout['orderId'] = $order->getIncrementId();
            $rerout['device'] = $device($order->getQuoteId(), $order->getStoreId());
            $rerout['shipments'] = $shipments;
            $updateResponse = $this->client->createReroute($rerout, $order);

            $this->logger->info("Case updated for order {$order->getIncrementId()}");
            $this->logger->info($this->jsonSerializer->serialize($updateResponse));

            $case->setEntries('hash', $newHashToValidateReroute);
            $updateCase = $this->updateCaseFactory->create();
            $case = $updateCase($case, $updateResponse);

            if ($case->getOrigData('guarantee') !== $case->getData('guarantee')) {
                $case->setStatus(\Signifyd\Connect\Model\Casedata::IN_REVIEW_STATUS);
                $updateOrder = $this->updateOrderFactory->create();
                $case = $updateOrder($case);
            }

            $this->casedataResourceModel->save($case);
        } catch (\Exception $e) {
            $this->logger->info('Failed to update case');
        }
    }
}
