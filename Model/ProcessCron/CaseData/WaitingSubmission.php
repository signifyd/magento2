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
use Signifyd\Connect\Model\Stripe\ReInitFactory as ReInitStripeFactory;
use Signifyd\Connect\Model\UpdateOrderFactory;

class WaitingSubmission
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
     * @var ReInitStripeFactory
     */
    protected $reInitStripeFactory;

    /**
     * @var PurchaseHelper
     */
    protected $purchaseHelper;

    /**
     * WaitingSubmission constructor.
     * @param ConfigHelper $configHelper
     * @param Logger $logger
     * @param OrderResourceModel $orderResourceModel
     * @param OrderFactory $orderFactory
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     * @param UpdateCaseFactory $updateCaseFactory
     * @param UpdateOrderFactory $updateOrderFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param StoreManagerInterface $storeManagerInterface
     * @param ReInitStripeFactory $reInitStripeFactory
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
        ReInitStripeFactory $reInitStripeFactory,
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
        $this->reInitStripeFactory = $reInitStripeFactory;
        $this->purchaseHelper = $purchaseHelper;
    }

    /**
     * @param array $waitingSubmissionCases
     * @return void
     */
    public function __invoke(array $waitingSubmissionCases)
    {
        /** @var \Signifyd\Connect\Model\Casedata $case */
        foreach ($waitingSubmissionCases as $case) {
            try {
                $order = $this->orderFactory->create();
                $this->signifydOrderResourceModel->load($order, $case->getData('order_id'));
                $this->storeManagerInterface->setCurrentStore($order->getStore()->getStoreId());

                $this->logger->debug(
                    "CRON: preparing for send case no: {$case['order_increment']}",
                    ['entity' => $case]
                );

                $reInitStripe = $this->reInitStripeFactory->create();
                $reInitStripe($order);

                try {
                    $this->casedataResourceModel->loadForUpdate($case, (string) $case->getData('entity_id'));

                    $caseModel = $this->purchaseHelper->processOrderData($order);
                    /** @var \Signifyd\Core\Response\SaleResponse $caseResponse */
                    $caseResponse = $this->purchaseHelper->postCaseToSignifyd($caseModel, $order);
                    $investigationId = $caseResponse->getSignifydId();

                    if (empty($investigationId) === false) {
                        $case->setCode($investigationId);
                        $case->setMagentoStatus(Casedata::IN_REVIEW_STATUS);
                        $case->setUpdated();
                        $this->casedataResourceModel->save($case);
                    }
                } catch (\Exception $e) {
                    $this->logger->error(
                        'CRON: Failed to save case data to database: '
                        . $e->getMessage()
                    );
                }
            } catch (\Exception $e) {
                $this->logger->error(
                    "CRON: Failed to process waiting submission case {$case->getId()}: "
                    . $e->getMessage()
                );
            } catch (\Error $e) {
                $this->logger->error(
                    "CRON: Failed to process waiting submission case {$case->getId()}: "
                    . $e->getMessage()
                );
            }
        }
    }
}