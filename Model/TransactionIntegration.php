<?php

namespace Signifyd\Connect\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Model\Api\TransactionsFactory;

class TransactionIntegration
{
    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    public $casedataResourceModel;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var CheckoutSession
     */
    public $checkoutSession;

    /**
     * @var TransactionsFactory
     */
    public $transactionsFactory;

    /**
     * @var Client
     */
    public $client;

    public $gatewayRefusedReason = null;

    public $gatewayStatusMessage = null;

    /**
     * CheckoutPaymentsDetailsHandler constructor.
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param CheckoutSession $checkoutSession
     * @param TransactionsFactory $transactionsFactory
     * @param ConfigHelper $configHelper
     * @param Client $client
     */
    public function __construct(
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        Logger $logger,
        StoreManagerInterface $storeManager,
        CheckoutSession $checkoutSession,
        TransactionsFactory $transactionsFactory,
        ConfigHelper $configHelper,
        Client $client
    ) {
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->transactionsFactory = $transactionsFactory;
        $this->configHelper = $configHelper;
        $this->client = $client;
    }

    public function submitToTransactionApi()
    {
        $quote = $this->checkoutSession->getQuote();

        if ($quote->isEmpty()) {
            return null;
        }

        $paymentMethod = $quote->getPayment()->getMethod();

        if (empty($paymentMethod)) {
            return null;
        }

        $policyName = $this->configHelper->getPolicyName(
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getId()
        );

        $isPreAuth = $this->configHelper->getIsPreAuth(
            $policyName,
            $paymentMethod,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getId()
        );

        if ($isPreAuth === false) {
            return null;
        }

        $quoteId = $quote->getId();
        $case = $this->casedataFactory->create();
        $this->casedataResourceModel->load($case, $quoteId, 'quote_id');

        if ($case->isEmpty()) {
            return null;
        }

        $entryReasonField = $paymentMethod . "RefusedReason";

        if ($case->getEntries($entryReasonField) === $this->gatewayRefusedReason) {
            $this->logger->info("Reason already send");
            return null;
        }

        $case->setEntries($entryReasonField, $this->gatewayRefusedReason);
        $this->casedataResourceModel->save($case);

        $data = [];
        $data['gatewayRefusedReason'] = $this->gatewayRefusedReason;
        $data['gatewayStatusMessage'] = $this->gatewayStatusMessage;
        $data['gateway'] = $paymentMethod;

        $makeTransactions = $this->transactionsFactory->create();
        $transaction = $makeTransactions($quote, $case->getCheckoutToken(), $data);

        $this->client->postTransactionToSignifyd($transaction, $quote);
        return null;
    }

    public function setGatewayRefusedReason($signifydReason)
    {
        $this->gatewayRefusedReason = $signifydReason;
    }

    public function setGatewayStatusMessage($signifydStatusMessage)
    {
        $this->gatewayStatusMessage = $signifydStatusMessage;
    }
}
