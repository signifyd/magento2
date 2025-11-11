<?php

namespace Signifyd\Connect\Plugin\Adyen\Payment\Controller\Webhook;

use Adyen\Payment\Controller\Webhook\Index as AdyenIndex;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\CasedataFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Signifyd\Connect\Model\Api\TransactionsFactory;
use Magento\Framework\App\Request\Http as RequestHttp;

class Index
{
    /**
     * @var CasedataRepositoryInterface
     */
    public $casedataRepository;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var QuoteFactory
     */
    public $quoteFactory;

    /**
     * @var QuoteResourceModel
     */
    public $quoteResourceModel;

    /**
     * @var TransactionsFactory
     */
    public $transactionsFactory;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var Client
     */
    public $client;

    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

    /**
     * @var RequestHttp
     */
    public $requestHttp;

    /**
     * Index constructor.
     *
     * @param CasedataRepositoryInterface $casedataRepository
     * @param CasedataFactory $casedataFactory
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param QuoteFactory $quoteFactory
     * @param QuoteResourceModel $quoteResourceModel
     * @param TransactionsFactory $transactionsFactory
     * @param ConfigHelper $configHelper
     * @param Client $client
     * @param JsonSerializer $jsonSerializer
     * @param RequestHttp $requestHttp
     */
    public function __construct(
        CasedataRepositoryInterface $casedataRepository,
        CasedataFactory $casedataFactory,
        Logger $logger,
        StoreManagerInterface $storeManager,
        QuoteFactory $quoteFactory,
        QuoteResourceModel $quoteResourceModel,
        TransactionsFactory $transactionsFactory,
        ConfigHelper $configHelper,
        Client $client,
        JsonSerializer $jsonSerializer,
        RequestHttp $requestHttp
    ) {
        $this->casedataRepository = $casedataRepository;
        $this->casedataFactory = $casedataFactory;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->quoteFactory = $quoteFactory;
        $this->quoteResourceModel = $quoteResourceModel;
        $this->transactionsFactory = $transactionsFactory;
        $this->configHelper = $configHelper;
        $this->client = $client;
        $this->jsonSerializer = $jsonSerializer;
        $this->requestHttp = $requestHttp;
    }

    /**
     * Transactions integration for Adyen version 9.0.0 or higher
     *
     * @param AdyenIndex $subject
     * @return void|null
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Signifyd\Core\Exceptions\ApiException
     * @throws \Signifyd\Core\Exceptions\InvalidClassException
     */
    public function beforeExecute(AdyenIndex $subject)
    {
        $policyName = $this->configHelper->getPolicyName(
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getId()
        );

        $isPreAuth = $this->configHelper->getIsPreAuth(
            $policyName,
            'adyen_cc',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getId()
        );

        $notificationItems = $this->jsonSerializer->unserialize($this->requestHttp->getContent());

        if ($isPreAuth === false && empty($notificationItems) === true) {
            return null;
        }

        if (isset($notificationItems['notificationItems']) &&
            isset($notificationItems['notificationItems'][0]) &&
            isset($notificationItems['notificationItems'][0]['NotificationRequestItem']) &&
            isset($notificationItems['notificationItems'][0]['NotificationRequestItem']['merchantReference']) &&
            isset($notificationItems['notificationItems'][0]['NotificationRequestItem']['success']) &&
            isset($notificationItems['notificationItems'][0]['NotificationRequestItem']['reason'])
        ) {
            $orderIncrement =
                $notificationItems['notificationItems'][0]['NotificationRequestItem']['merchantReference'];
            $isSuccess = $notificationItems['notificationItems'][0]['NotificationRequestItem']['success'];
            $reason = $notificationItems['notificationItems'][0]['NotificationRequestItem']['reason'];

            /** @var \Signifyd\Connect\Model\Casedata $case */
            $case = $this->casedataRepository->getByOrderId($orderIncrement);

            if ($case->isEmpty() === false && $isSuccess === "false") {
                if ($case->getEntries('AdyenRefusedReason') == $reason) {
                    return null;
                }

                $this->logger->info(
                    "collecting Adyen pre-authorization transaction data for case " . $case->getCode(),
                    ['entity' => $case]
                );

                $adyenData = [];
                $case->setEntries("AdyenRefusedReason", $reason);
                $this->casedataRepository->save($case);

                switch ($reason) {
                    case "Expired Card":
                        $signifydReason = "EXPIRED_CARD";
                        break;

                    case "Invalid Card Number":
                        $signifydReason = "INCORRECT_NUMBER";
                        break;

                    case "Not enough balance":
                        $signifydReason = "INSUFFICIENT_FUNDS";
                        break;

                    case "Acquirer Fraud":
                    case "FRAUD":
                    case "FRAUD-CANCELLED":
                    case "Issuer Suspected Fraud":
                        $signifydReason = "FRAUD_DECLINE";
                        break;

                    case "CVC Declined":
                        $signifydReason = "INVALID_CVC";
                        break;

                    case "Restricted Card":
                        $signifydReason = "RESTRICTED_CARD";
                        break;

                    default:
                        $signifydReason = "CARD_DECLINED";
                        break;
                }

                $adyenData['gatewayRefusedReason'] = $signifydReason;
                $adyenData['gateway'] = 'adyen_cc';

                if (isset($notificationItems['notificationItems'][0]['NotificationRequestItem']['additionalData'])) {
                    $adyenData['cardLast4'] =
                        $notificationItems['notificationItems'][0]
                        ['NotificationRequestItem']['additionalData']['cardSummary'] ?? null;

                    if (isset($notificationItems['notificationItems']
                        [0]['NotificationRequestItem']['additionalData']['expiryDate'])) {
                        $expiryDate = $notificationItems['notificationItems'][0]
                        ['NotificationRequestItem']['additionalData']['expiryDate'];
                        $expiryDateArray = explode('/', $expiryDate);
                        $adyenData['cardExpiryMonth'] = $expiryDateArray[0];
                        $adyenData['cardExpiryYear'] = $expiryDateArray[1];
                    }
                }

                $quote = $this->quoteFactory->create();
                $this->quoteResourceModel->load($quote, $case->getQuoteId());
                $makeTransactions = $this->transactionsFactory->create();
                $transaction = $makeTransactions($quote, $case->getCheckoutToken(), $adyenData);

                $this->client->postTransactionToSignifyd($transaction, $quote);
            }
        }
    }
}
