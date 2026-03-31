<?php

namespace Signifyd\Connect\Plugin\Adyen\Payment\Gateway\Http\Client;

use Adyen\Payment\Gateway\Http\Client\TransactionPayment as AdyenTransactionPayment;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\Api\TransactionsFactory;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;

class TransactionPayment
{
    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    public $casedataResourceModel;

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
     * @var Client
     */
    public $client;

    /**
     * TransactionPayment construct.
     *
     * @param StoreManagerInterface $storeManager
     * @param Logger $logger
     * @param ConfigHelper $configHelper
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param QuoteFactory $quoteFactory
     * @param QuoteResourceModel $quoteResourceModel
     * @param TransactionsFactory $transactionsFactory
     * @param Client $client
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Logger $logger,
        ConfigHelper $configHelper,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        QuoteFactory $quoteFactory,
        QuoteResourceModel $quoteResourceModel,
        TransactionsFactory $transactionsFactory,
        Client $client
    )
    {
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->configHelper = $configHelper;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->quoteFactory = $quoteFactory;
        $this->quoteResourceModel = $quoteResourceModel;
        $this->transactionsFactory = $transactionsFactory;
        $this->client = $client;
    }


    /**
     * After place request method.
     *
     * @param AdyenTransactionPayment $subject
     * @param mixed $response
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function afterPlaceRequest(AdyenTransactionPayment $subject, $response)
    {
        try {
            $storeId = $this->storeManager->getStore()->getId();
            $policyName = $this->configHelper->getPolicyName(
                \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
                $storeId
            );
            $isPreAuth = $this->configHelper->getIsPreAuth(
                $policyName,
                'adyen_cc',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
                $storeId
            );

            if ($isPreAuth === false && empty($response) === true) {
                return null;
            }

            if (isset($response) &&
                isset($response[0]) &&
                isset($response[0]['merchantReference']) &&
                isset($response[0]['resultCode']) &&
                isset($response[0]['refusalReason'])
            ) {
                $orderIncrement = $response[0]['merchantReference'];
                $resultCode = $response[0]['resultCode'];
                $reason = $response[0]['refusalReason'];

                /** @var \Signifyd\Connect\Model\Casedata $case */
                $case = $this->casedataFactory->create();
                $this->casedataResourceModel->load($case, $orderIncrement, 'order_increment');

                if ($case->isEmpty() === false && $resultCode === "Refused") {
                    if ($case->getEntries('AdyenRefusedReason') == $reason) {
                        return null;
                    }

                    $this->logger->info(
                        "collecting Adyen pre-authorization transaction data for case " . $case->getCode(),
                        ['entity' => $case]
                    );

                    $adyenData = [];
                    $case->setEntries("AdyenRefusedReason", $reason);
                    $this->casedataResourceModel->save($case);

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

                    if (isset($response[0]['additionalData'])) {
                        $adyenData['cardLast4'] = $response[0]['additionalData']['cardSummary'] ?? null;

                        if (isset($response[0]['additionalData']['expiryDate'])) {
                            $expiryDate = $response[0]['additionalData']['expiryDate'];
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
        } catch (\Exception $e) {
            $this->logger->info("Failed to sent transaction: " .  $e->getMessage());
        } catch (\Error $e) {
            $this->logger->info("Failed to sent transaction: " .  $e->getMessage());
        }

        return $response;
    }
}