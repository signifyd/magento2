<?php

namespace Signifyd\Connect\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Api\Core\Client;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Model\Api\TransactionsFactory;

class TransactionIntegration
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

    /**
     * @var null
     */
    public $gatewayRefusedReason = null;

    /**
     * @var null
     */
    public $gatewayStatusMessage = null;

    /**
     * TransactionIntegration constructor.
     *
     * @param CasedataRepositoryInterface $casedataRepository
     * @param CasedataFactory $casedataFactory
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param CheckoutSession $checkoutSession
     * @param TransactionsFactory $transactionsFactory
     * @param ConfigHelper $configHelper
     * @param Client $client
     */
    public function __construct(
        CasedataRepositoryInterface $casedataRepository,
        CasedataFactory $casedataFactory,
        Logger $logger,
        StoreManagerInterface $storeManager,
        CheckoutSession $checkoutSession,
        TransactionsFactory $transactionsFactory,
        ConfigHelper $configHelper,
        Client $client
    ) {
        $this->casedataRepository = $casedataRepository;
        $this->casedataFactory = $casedataFactory;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->transactionsFactory = $transactionsFactory;
        $this->configHelper = $configHelper;
        $this->client = $client;
    }

    /**
     * Submit to transaction api method.
     *
     * @return null
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Signifyd\Core\Exceptions\ApiException
     * @throws \Signifyd\Core\Exceptions\InvalidClassException
     */
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

        $case = $this->casedataRepository->getByQuoteId($quote->getId());
        if ($case->isEmpty()) {
            return null;
        }

        $entryReasonField = $paymentMethod . "RefusedReason";

        if ($case->getEntries($entryReasonField) === $this->gatewayRefusedReason) {
            $this->logger->info("Reason already send");
            return null;
        }

        $case->setEntries($entryReasonField, $this->gatewayRefusedReason);
        $this->casedataRepository->save($case);

        $data = [];
        $data['gatewayRefusedReason'] = $this->gatewayRefusedReason;
        $data['gatewayStatusMessage'] = $this->gatewayStatusMessage;
        $data['gateway'] = $paymentMethod;

        $makeTransactions = $this->transactionsFactory->create();
        $transaction = $makeTransactions($quote, $case->getCheckoutToken(), $data);

        $this->client->postTransactionToSignifyd($transaction, $quote);
        return null;
    }

    /**
     * Set gateway refused reason method.
     *
     * @param mixed $signifydReason
     * @return void
     */
    public function setGatewayRefusedReason($signifydReason)
    {
        $this->gatewayRefusedReason = $signifydReason;
    }

    /**
     * Set gateway status message method.
     *
     * @param mixed $signifydStatusMessage
     * @return void
     */
    public function setGatewayStatusMessage($signifydStatusMessage)
    {
        $this->gatewayStatusMessage = $signifydStatusMessage;
    }
}
