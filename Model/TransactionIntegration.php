<?php

namespace Signifyd\Connect\Model;

use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Checkout\Model\Cart as CheckoutCart;
use Signifyd\Connect\Model\Api\TransactionsFactory;

class TransactionIntegration
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
     * @var CheckoutCart
     */
    protected $checkoutCart;

    /**
     * @var TransactionsFactory
     */
    protected $transactionsFactory;

    /**
     * @var Client
     */
    protected $client;

    protected $gatewayRefusedReason = null;

    protected $gatewayStatusMessage = null;

    /**
     * CheckoutPaymentsDetailsHandler constructor.
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param CheckoutCart $checkoutCart
     * @param TransactionsFactory $transactionsFactory
     * @param ConfigHelper $configHelper
     * @param Client $client
     */
    public function __construct(
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        Logger $logger,
        StoreManagerInterface $storeManager,
        CheckoutCart $checkoutCart,
        TransactionsFactory $transactionsFactory,
        ConfigHelper $configHelper,
        Client $client
    ) {
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->checkoutCart = $checkoutCart;
        $this->transactionsFactory = $transactionsFactory;
        $this->configHelper = $configHelper;
        $this->client = $client;
    }

    public function submitToTransactionApi()
    {
        $quote = $this->checkoutCart->getQuote();

        if (isset($quote) === false) {
            return null;
        }

        $paymentMethod = $quote->getPayment()->getMethod();

        if (isset($paymentMethod) === false) {
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
