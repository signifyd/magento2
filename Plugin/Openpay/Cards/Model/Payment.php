<?php

namespace Signifyd\Connect\Plugin\Openpay\Cards\Model;

use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\Api\TransactionsFactory;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\CasedataFactory;
use Magento\Store\Model\StoreManagerInterface;
use Openpay\Cards\Model\Payment as OpenpayPayment;
use Magento\Checkout\Model\Session as CheckoutSession;

class Payment
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
     * @var CheckoutSession
     */
    public $checkoutSession;

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
     * Payment constructor.
     *
     * @param CasedataRepositoryInterface $casedataRepository
     * @param CasedataFactory $casedataFactory
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param TransactionsFactory $transactionsFactory
     * @param ConfigHelper $configHelper
     * @param Client $client
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        CasedataRepositoryInterface $casedataRepository,
        CasedataFactory $casedataFactory,
        Logger $logger,
        StoreManagerInterface $storeManager,
        TransactionsFactory $transactionsFactory,
        ConfigHelper $configHelper,
        Client $client,
        CheckoutSession $checkoutSession
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
     * Before error method.
     *
     * @param OpenpayPayment $subject
     * @param mixed $e
     * @return null
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function beforeError(OpenpayPayment $subject, $e)
    {
        $policyName = $this->configHelper->getPolicyName(
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getId()
        );

        $isPreAuth = $this->configHelper->getIsPreAuth(
            $policyName,
            'openpay_cards',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getId()
        );

        $quote = $this->checkoutSession->getQuote();

        if ($isPreAuth === false || $quote->isEmpty()) {
            return null;
        }

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->casedataRepository->getByQuoteId($quote->getId());

        if ($case->isEmpty()) {
            return null;
        }

        $signifydReason = null;

        switch ($e->getCode()) {
            case '2004':
                $signifydReason = 'INVALID_NUMBER';
                break;
            case '2005':
                $signifydReason = 'INVALID_EXPIRY_DATE';
                break;
            case '2006':
                $signifydReason = 'INCORRECT_CVC';
                break;
            case '2007':
                $signifydReason = 'TEST_CARD_DECLINE';
                break;
            case '2009':
                $signifydReason = 'INVALID_CVC';
                break;
            case '3005':
            case '2010':
                $signifydReason = 'FRAUD_DECLINE';
                break;
            case '3004':
            case '3009':
                $signifydReason = 'STOLEN_CARD';
                break;
            case '3001':
                $signifydReason = 'CARD_DECLINED';
                break;
            case '3002':
                $signifydReason = 'EXPIRED_CARD';
                break;
            case '3003':
                $signifydReason = 'INSUFFICIENT_FUNDS';
                break;
            case '3010':
            case '3011':
            case '2008':
            case '2011':
                $signifydReason = 'RESTRICTED_CARD';
                break;
            case '3012':
                $signifydReason = 'CALL_ISSUER';
                break;
            case '3006':
                $signifydReason = 'PROCESSING_ERROR';
                break;
        }

        if ($case->getEntries('OpenPayRefusedReason') == $signifydReason) {
            $this->logger->info("Reason already send", ['entity' => $case]);
            return null;
        }

        $openPayData = [];
        $openPayData['gatewayRefusedReason'] = $signifydReason;
        $openPayData['gatewayStatusMessage'] = $e->getDescription();
        $openPayData['gateway'] = 'openpay_cards';

        $case->setEntries("OpenPayRefusedReason", $signifydReason);
        $this->casedataRepository->save($case);
        $makeTransactions = $this->transactionsFactory->create();
        $transaction = $makeTransactions($quote, $case->getCheckoutToken(), $openPayData);

        $this->client->postTransactionToSignifyd($transaction, $quote);
        return null;
    }
}
