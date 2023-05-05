<?php

namespace Signifyd\Connect\Model\ProcessCron\CaseData;

use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\Casedata\UpdateCaseFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;
use Signifyd\Connect\Model\UpdateOrderFactory;

class AsyncWaiting
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var SignifydOrderResourceModel
     */
    protected $signifydOrderResourceModel;

    /**
     * @var UpdateCaseFactory
     */
    protected $updateCaseFactory;

    /**
     * @var UpdateOrderFactory
     */
    protected $updateOrderFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var \StripeIntegration\Payments\Model\Config
     */
    protected $stripeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var PurchaseHelper
     */
    protected $purchaseHelper;

    /**
     * AsyncWaiting constructor.
     * @param ConfigHelper $configHelper
     * @param Logger $logger
     * @param OrderResourceModel $orderResourceModel
     * @param OrderFactory $orderFactory
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     * @param UpdateCaseFactory $updateCaseFactory
     * @param UpdateOrderFactory $updateOrderFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param StoreManagerInterface $storeManagerInterface
     * @param PurchaseHelper $purchaseHelper
     */
    public function __construct(
        ConfigHelper $configHelper,
        Logger $logger,
        OrderResourceModel $orderResourceModel,
        OrderFactory $orderFactory,
        SignifydOrderResourceModel $signifydOrderResourceModel,
        UpdateCaseFactory $updateCaseFactory,
        UpdateOrderFactory $updateOrderFactory,
        CasedataResourceModel $casedataResourceModel,
        StoreManagerInterface $storeManagerInterface,
        PurchaseHelper $purchaseHelper
    ) {
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->orderResourceModel = $orderResourceModel;
        $this->orderFactory = $orderFactory;
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;
        $this->updateCaseFactory = $updateCaseFactory;
        $this->updateOrderFactory = $updateOrderFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->purchaseHelper = $purchaseHelper;
    }

    /**
     * @param array $asyncWaitingCases
     * @return void
     */
    public function __invoke(array $asyncWaitingCases)
    {
        /** @var \Signifyd\Connect\Model\Casedata $case */
        foreach ($asyncWaitingCases as $case) {
            try {
                $order = $this->orderFactory->create();
                $this->signifydOrderResourceModel->load($order, $case->getData('order_id'));
                $this->storeManagerInterface->setCurrentStore($order->getStore()->getStoreId());

                $this->logger->debug(
                    "CRON: preparing for send case no: {$case->getOrderIncrement()}",
                    ['entity' => $case]
                );

                if (empty($case->getEntries('async_action')) === false &&
                    $case->getEntries('async_action') === 'delete'
                ) {
                    $this->casedataResourceModel->delete($case);
                }

                $order->setData('origin_store_code', $case->getData('origin_store_code'));
                $caseModel = $this->purchaseHelper->processOrderData($order);
                $avsCode = $caseModel['transactions'][0]['verifications']['avsResponseCode'];
                $cvvCode = $caseModel['transactions'][0]['verifications']['cvvResponseCode'];
                $retries = $case->getData('retries');

                if ($retries >= 5 || empty($avsCode) === false && empty($cvvCode) === false) {
                    try {
                        $this->casedataResourceModel->loadForUpdate($case, (string) $case->getData('entity_id'));

                        $case->setMagentoStatus(Casedata::WAITING_SUBMISSION_STATUS);
                        $case->setUpdated();

                        $this->casedataResourceModel->save($case);
                    } catch (\Exception $e) {
                        $this->logger->error('CRON: Failed to save case data to database: ' . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error(
                    "CRON: Failed to process async waiting case {$case->getId()}: "
                    . $e->getMessage()
                );
            } catch (\Error $e) {
                $this->logger->error(
                    "CRON: Failed to process async waiting case {$case->getId()}: "
                    . $e->getMessage()
                );
            }
        }
    }
}