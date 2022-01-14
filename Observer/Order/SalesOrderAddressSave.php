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

        $this->logger->info("Send case to update");

        $updateData = [];
        $recipients = $this->purchaseHelper->makeRecipient($order);
        $recipientJson = $this->jsonSerializer->serialize($recipients);
        $newtHash = sha1($recipientJson);
        $currentHash = $case->getEntries('hash');

        if ($newtHash == $currentHash) {
            $this->logger->info("Case already updated");
            return;
        }

        $updateData['recipient'] = $recipients[0];
        $updateResponse = $this->purchaseHelper->updateCaseSignifyd($updateData, $order, $case->getCode());

        $case->setEntries('hash', $newtHash);
        $case->updateCase($updateResponse);
        $case->updateOrder();
        $this->casedataResourceModel->save($case);
    }
}
