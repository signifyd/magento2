<?php

namespace Signifyd\Connect\Model\ProcessCron;

use Magento\Sales\Model\OrderFactory;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\Api\DeviceFactory;
use Signifyd\Connect\Model\Api\ShipmentsFactory;
use Signifyd\Connect\Model\Casedata\UpdateCaseFactory;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;
use Signifyd\Connect\Model\UpdateOrderFactory;
use Signifyd\Models\PaymentUpdateFactory;
use Signifyd\Connect\Model\ResourceModel\Reroute as RerouteResourceModel;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;

class Reroute
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
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var SignifydOrderResourceModel
     */
    protected $signifydOrderResourceModel;

    /**
     * @var RerouteResourceModel
     */
    protected $rerouteResourceModel;

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
     * @param OrderFactory $orderFactory
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     * @param RerouteResourceModel $rerouteResourceModel
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
        DeviceFactory $deviceFactory,
        OrderFactory $orderFactory,
        SignifydOrderResourceModel $signifydOrderResourceModel,
        RerouteResourceModel $rerouteResourceModel
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
        $this->orderFactory = $orderFactory;
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;
        $this->rerouteResourceModel = $rerouteResourceModel;
    }

    /**
     * @param \Signifyd\Connect\Model\Reroute $reroute
     * @return void
     */
    public function __invoke(\Signifyd\Connect\Model\Reroute $reroute)
    {
        try {
            $orderId = $reroute->getOrderId();
            $case = $this->casedataFactory->create();
            $this->casedataResourceModel->loadForUpdate($case, $orderId, 'order_id');
            $order = $this->orderFactory->create();
            $this->signifydOrderResourceModel->load($order, $orderId);

            $this->logger->info("Send case update for order {$order->getIncrementId()}");

            $makeShipments = $this->shipmentsFactory->create();
            $shipments = $makeShipments($order);
            $recipient = $shipments[0]['destination'];
            $recipientJson = $this->jsonSerializer->serialize($recipient);
            $newHashToValidateReroute = sha1($recipientJson);
            $currentHashToValidateReroute = $case->getEntries('hash');

            if ($newHashToValidateReroute == $currentHashToValidateReroute) {
                $this->logger->info("No data changes, will not update order {$order->getIncrementId()}");
                $reroute->setMagentoStatus(\Signifyd\Connect\Model\Fulfillment::COMPLETED_STATUS);
                $this->rerouteResourceModel->save($reroute);
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

            if ($updateResponse !== false) {
                $reroute->setMagentoStatus(\Signifyd\Connect\Model\Fulfillment::COMPLETED_STATUS);
                $this->rerouteResourceModel->save($reroute);
            }

            $case->setEntries('hash', $newHashToValidateReroute);
            $updateCase = $this->updateCaseFactory->create();
            $case = $updateCase($case, $updateResponse);

            if ($case->getOrigData('signifyd_status') !== $case->getData('signifyd_status')) {
                $case->setStatus(\Signifyd\Connect\Model\Casedata::IN_REVIEW_STATUS);
                $updateOrder = $this->updateOrderFactory->create();
                $case = $updateOrder($case);
            }

            $this->casedataResourceModel->save($case);
        } catch (\Exception $e) {
            $this->logger->error(
                "Failed to process Reroute to order {$reroute->getOrderId()}: "
                . $e->getMessage()
            );
        } catch (\Error $e) {
            $this->logger->error(
                "Failed to process Reroute to order {$reroute->getOrderId()}: "
                . $e->getMessage()
            );
        }
    }
}