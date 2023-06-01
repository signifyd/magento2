<?php

namespace Signifyd\Connect\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Quote\Api\CartRepositoryInterface;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;
use Signifyd\Connect\Model\Api\TransactionsFactory;

class ThreeDsIntegration
{
    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepositoryInterface;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var SignifydOrderResourceModel
     */
    protected $signifydOrderResourceModel;

    /**
     * @var TransactionsFactory
     */
    protected $transactionsFactory;

    /**
     * @var Client
     */
    protected $client;

    protected $signifydFields = ['eci', 'cavv', 'version', 'transStatus', 'transStatusReason', 'acsOperatorId',
        'dsTransId', 'threeDsServerTransId', 'cavvAlgorithm', 'exemptionIndicator', 'timestamp'];

    /**
     * CheckoutPaymentsDetailsHandler constructor.
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param Logger $logger
     * @param ConfigHelper $configHelper
     * @param StoreManagerInterface $storeManager
     * @param CheckoutSession $checkoutSession
     * @param JsonSerializer $jsonSerializer
     * @param CartRepositoryInterface $cartRepositoryInterface
     * @param OrderFactory $orderFactory
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     * @param TransactionsFactory $transactionsFactory
     * @param Client $client
     */
    public function __construct(
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        Logger $logger,
        ConfigHelper $configHelper,
        StoreManagerInterface $storeManager,
        CheckoutSession $checkoutSession,
        JsonSerializer $jsonSerializer,
        CartRepositoryInterface $cartRepositoryInterface,
        OrderFactory $orderFactory,
        SignifydOrderResourceModel $signifydOrderResourceModel,
        TransactionsFactory $transactionsFactory,
        Client $client
    ) {
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->logger = $logger;
        $this->configHelper = $configHelper;
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->jsonSerializer = $jsonSerializer;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->orderFactory = $orderFactory;
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;
        $this->transactionsFactory = $transactionsFactory;
        $this->client = $client;
    }


    public function setThreeDsData(array $threeDsData, $quoteId = null)
    {
        if (empty($threeDsData)) {
            return;
        }

        if (isset($quoteId)) {
            $quote = $this->cartRepositoryInterface->get($quoteId);

            if (isset($quote) === false) {
                $this->logger->info("Error getting quote");

                return;
            }
        } else {
            $quote = $this->checkoutSession->getQuote();

            if (isset($quote) === false) {
                $this->logger->info("Quote not found magento checkout session");

                return;
            }
        }

        if ($this->configHelper->isEnabled($quote) === false) {
            return;
        }

        $quoteId = $quote->getId();

        if (isset($quoteId) === false) {
            $this->logger->info("Quote id not found");

            return;
        }

        $threeDsData = $this->validateFields($threeDsData);

        $case = $this->casedataFactory->create();
        $this->casedataResourceModel->load($case, $quoteId, 'quote_id');

        $case->setData('quote_id', $quoteId);
        $case->setEntries('threeDs', $this->jsonSerializer->serialize($threeDsData));
        $this->casedataResourceModel->save($case);

        $this->validateSentTransaction($quoteId);
    }

    public function validateSentTransaction($quoteId)
    {
        try {
            $case = $this->casedataFactory->create();
            $this->casedataResourceModel->load($case, $quoteId, 'quote_id');

            $orderId = $case->getData('order_id');
            $orderIncrementId = $case->getData('order_increment');

            if ($case->getPolicyName() == Casedata::PRE_AUTH &&
                isset($orderId) &&
                isset($orderIncrementId)
            ) {
                $order = $this->orderFactory->create();
                $this->signifydOrderResourceModel->load($order, $orderId);

                if (isset($order) === false) {
                    return;
                }

                $this->logger->info("Sending pre_auth transaction with ThreeDs data to Signifyd for order
                            {$case->getOrderIncrement()}");
                $makeTransactions = $this->transactionsFactory->create();
                $saleTransaction = [];
                $saleTransaction['checkoutId'] = $case->getCheckoutToken();
                $saleTransaction['orderId'] = $orderIncrementId;
                $saleTransaction['transactions'] = $makeTransactions($order);
                $this->client->postTransactionToSignifyd($saleTransaction, $order);
            }
        } catch (\Exception $e) {
            $this->logger->info("Failed to send transaction: " . $e->getMessage());
        } catch (\Error $e) {
            $this->logger->info("Failed to send transaction: " . $e->getMessage());
        }
    }

    public function validateFields(array $threeDsData)
    {
        $invalidFields = [];

        foreach ($threeDsData as $field => $value) {
            if (in_array($field, $this->signifydFields) === false) {
                $invalidFields[] = $field;
                unset($threeDsData[$field]);
            }
        }

        if (empty($invalidFields) === false) {
            $this->logger->info("The following invalid fields have been removed: " .
                implode(',', $invalidFields));
        }

        return $threeDsData;
    }
}
