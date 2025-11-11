<?php

namespace Signifyd\Connect\Model\ProcessCron\CaseData;

use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\Casedata\UpdateCaseFactory;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;
use Signifyd\Connect\Model\Stripe\ReInitFactory as ReInitStripeFactory;
use Signifyd\Connect\Model\UpdateOrderFactory;
use Signifyd\Connect\Model\Api\SaleOrderFactory;

class WaitingSubmission
{
    /**
     * @var CasedataRepositoryInterface
     */
    public $casedataRepository;

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
     * @var StoreManagerInterface
     */
    public $storeManagerInterface;

    /**
     * @var ReInitStripeFactory
     */
    public $reInitStripeFactory;

    /**
     * @var SaleOrderFactory
     */
    public $saleOrderFactory;

    /**
     * @var Client
     */
    public $client;

    /**
     * WaitingSubmission constructor.
     *
     * @param CasedataRepositoryInterface $casedataRepository
     * @param ConfigHelper $configHelper
     * @param Logger $logger
     * @param OrderResourceModel $orderResourceModel
     * @param OrderFactory $orderFactory
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     * @param UpdateCaseFactory $updateCaseFactory
     * @param UpdateOrderFactory $updateOrderFactory
     * @param StoreManagerInterface $storeManagerInterface
     * @param ReInitStripeFactory $reInitStripeFactory
     * @param SaleOrderFactory $saleOrderFactory
     * @param Client $client
     */
    public function __construct(
        CasedataRepositoryInterface $casedataRepository,
        ConfigHelper $configHelper,
        Logger $logger,
        OrderResourceModel $orderResourceModel,
        OrderFactory $orderFactory,
        SignifydOrderResourceModel $signifydOrderResourceModel,
        UpdateCaseFactory $updateCaseFactory,
        UpdateOrderFactory $updateOrderFactory,
        StoreManagerInterface $storeManagerInterface,
        ReInitStripeFactory $reInitStripeFactory,
        SaleOrderFactory $saleOrderFactory,
        Client $client
    ) {
        $this->casedataRepository = $casedataRepository;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->orderResourceModel = $orderResourceModel;
        $this->orderFactory = $orderFactory;
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;
        $this->updateCaseFactory = $updateCaseFactory;
        $this->updateOrderFactory = $updateOrderFactory;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->reInitStripeFactory = $reInitStripeFactory;
        $this->saleOrderFactory = $saleOrderFactory;
        $this->client = $client;
    }

    /**
     * Invoke method.
     *
     * @param array $waitingSubmissionCases
     * @return void
     */
    public function __invoke(array $waitingSubmissionCases)
    {
        $previousStore = $this->storeManagerInterface->getStore()->getId();

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
                    $this->casedataRepository->loadForUpdate($case, (string) $case->getData('entity_id'));
                    $saleOrder = $this->saleOrderFactory->create();
                    $order->setData('origin_store_code', $case->getData('origin_store_code'));
                    $caseModel = $saleOrder($order);
                    /** @var \Signifyd\Core\Response\SaleResponse $caseResponse */
                    $caseResponse = $this->client->postCaseToSignifyd($caseModel, $order);
                    $investigationId = $caseResponse->getSignifydId();

                    if (empty($investigationId) === false) {
                        $case->setCode($investigationId);
                        $case->setMagentoStatus(Casedata::IN_REVIEW_STATUS);
                        $case->setUpdated();
                        $this->casedataRepository->save($case);
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

        $this->storeManagerInterface->setCurrentStore($previousStore);
    }
}
