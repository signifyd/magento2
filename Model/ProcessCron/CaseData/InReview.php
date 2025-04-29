<?php

namespace Signifyd\Connect\Model\ProcessCron\CaseData;

use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\Casedata\UpdateCaseFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;
use Signifyd\Connect\Model\Stripe\ReInitFactory as ReInitStripeFactory;
use Signifyd\Connect\Model\UpdateOrderFactory;

class InReview
{

    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

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
     * @var ReInitStripeFactory
     */
    public $reInitStripeFactory;

    /**
     * @var Client
     */
    public $client;

    /**
     * InReview constructor.
     * 
     * @param JsonSerializer $jsonSerializer
     * @param Logger $logger
     * @param ConfigHelper $configHelper
     * @param OrderResourceModel $orderResourceModel
     * @param OrderFactory $orderFactory
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     * @param UpdateCaseFactory $updateCaseFactory
     * @param UpdateOrderFactory $updateOrderFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param StoreManagerInterface $storeManagerInterface
     * @param ReInitStripeFactory $reInitStripeFactory
     * @param Client $client
     */
    public function __construct(
        JsonSerializer $jsonSerializer,
        Logger $logger,
        ConfigHelper $configHelper,
        OrderResourceModel $orderResourceModel,
        OrderFactory $orderFactory,
        SignifydOrderResourceModel $signifydOrderResourceModel,
        UpdateCaseFactory $updateCaseFactory,
        UpdateOrderFactory $updateOrderFactory,
        CasedataResourceModel $casedataResourceModel,
        StoreManagerInterface $storeManagerInterface,
        ReInitStripeFactory $reInitStripeFactory,
        Client $client
    ) {
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
        $this->configHelper = $configHelper;
        $this->orderResourceModel = $orderResourceModel;
        $this->orderFactory = $orderFactory;
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;
        $this->updateCaseFactory = $updateCaseFactory;
        $this->updateOrderFactory = $updateOrderFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->reInitStripeFactory = $reInitStripeFactory;
        $this->client = $client;
    }

    /**
     * @param array $inReviewCases
     * @return void
     */
    public function __invoke(array $inReviewCases)
    {
        $previousStore = $this->storeManagerInterface->getStore()->getId();

        /** @var \Signifyd\Connect\Model\Casedata $case */
        foreach ($inReviewCases as $case) {
            try {
                $order = $this->orderFactory->create();
                $this->signifydOrderResourceModel->load($order, $case->getData('order_id'));
                $this->storeManagerInterface->setCurrentStore($order->getStore()->getStoreId());

                $this->logger->debug(
                    "CRON: preparing for review case no: {$case['order_increment']}",
                    ['entity' => $case]
                );

                $reInitStripe = $this->reInitStripeFactory->create();
                $reInitStripe($order);

                try {
                    $response = $this->client->getSignifydSaleApi($case)->getCase($case->getData('order_increment'));
                    
                    $this->logger->info(
                        "Get case api response: " . $this->jsonSerializer->serialize($response), ['entity' => $case]
                    );

                    $this->casedataResourceModel->loadForUpdate($case, (string) $case->getData('entity_id'));

                    $currentCaseHash = sha1(implode(',', $case->getData()));
                    $updateCase = $this->updateCaseFactory->create();
                    $case = $updateCase($case, $response);
                    $newCaseHash = sha1(implode(',', $case->getData()));

                    if ($currentCaseHash == $newCaseHash) {
                        $this->logger->info(
                            "CRON: Case {$case->getId()} already update with this data," .
                            " no action will be taken",
                            ['entity' => $case]
                        );

                        // Triggering case save to unlock case
                        $this->casedataResourceModel->save($case);

                        continue;
                    }

                    $updateOrder = $this->updateOrderFactory->create();
                    $case = $updateOrder($case);

                    $this->casedataResourceModel->save($case);
                } catch (\Exception $e) {
                    // Triggering case save to unlock case
                    if ($case instanceof \Signifyd\Connect\Model\Casedata) {
                        $this->casedataResourceModel->save($case);
                    }

                    $this->logger->error(
                        'CRON: Failed to save case data to database: '
                        . $e->getMessage(),
                        ['entity' => $case]
                    );
                }
            } catch (\Exception $e) {
                $this->logger->error(
                    "CRON: Failed to process in review case {$case->getId()}: "
                    . $e->getMessage()
                );
            } catch (\Error $e) {
                $this->logger->error(
                    "CRON: Failed to process in review case {$case->getId()}: "
                    . $e->getMessage()
                );
            }
        }

        $this->storeManagerInterface->setCurrentStore($previousStore);
    }
}
