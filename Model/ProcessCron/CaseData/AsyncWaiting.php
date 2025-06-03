<?php

namespace Signifyd\Connect\Model\ProcessCron\CaseData;

use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\Casedata\UpdateCaseFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;
use Signifyd\Connect\Model\UpdateOrderFactory;
use Signifyd\Connect\Model\Api\SaleOrderFactory;
use Signifyd\Connect\Model\PaymentVerificationFactory;

class AsyncWaiting
{
    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var OrderResourceModel
     */
    public $orderResourceModel;

    /**
     * @var OrderFactory
     */
    public $orderFactory;

    /**
     * @var SignifydOrderResourceModel
     */
    public $signifydOrderResourceModel;

    /**
     * @var UpdateCaseFactory
     */
    public $updateCaseFactory;

    /**
     * @var UpdateOrderFactory
     */
    public $updateOrderFactory;

    /**
     * @var CasedataResourceModel
     */
    public $casedataResourceModel;

    /**
     * @var StoreManagerInterface
     */
    public $storeManagerInterface;

    /**
     * @var SaleOrderFactory
     */
    public $saleOrderFactory;

    /**
     * @var PaymentVerificationFactory
     */
    public $paymentVerificationFactory;

    /**
     * AsyncWaiting constructor.
     *
     * @param ConfigHelper $configHelper
     * @param Logger $logger
     * @param OrderResourceModel $orderResourceModel
     * @param OrderFactory $orderFactory
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     * @param UpdateCaseFactory $updateCaseFactory
     * @param UpdateOrderFactory $updateOrderFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param StoreManagerInterface $storeManagerInterface
     * @param SaleOrderFactory $saleOrderFactory
     * @param PaymentVerificationFactory $paymentVerificationFactory
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
        SaleOrderFactory $saleOrderFactory,
        PaymentVerificationFactory $paymentVerificationFactory
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
        $this->saleOrderFactory = $saleOrderFactory;
        $this->paymentVerificationFactory = $paymentVerificationFactory;
    }

    /**
     * Invoke method.
     *
     * @param array $asyncWaitingCases
     * @return void
     */
    public function __invoke(array $asyncWaitingCases)
    {
        $previousStore = $this->storeManagerInterface->getStore()->getId();

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

                /** @var \Signifyd\Connect\Model\Payment\Base\AsyncChecker $asyncCheck */
                $asyncCheck = $this->paymentVerificationFactory->createPaymentAsyncChecker(
                    $order->getPayment()->getMethod()
                );

                if ($asyncCheck($order, $case)) {
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

        $this->storeManagerInterface->setCurrentStore($previousStore);
    }
}
