<?php

namespace Signifyd\Connect\Plugin\Openpay\Cards\Model;

use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\Api\TransactionsFactory;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Openpay\Cards\Model\Payment as OpenpayPayment;
use Magento\Checkout\Model\Cart as CheckoutCart;

class Payment
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
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var Client
     */
    protected $client;

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

    /**
     * @param OpenpayPayment $subject
     * @param $e
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

        $quote = $this->checkoutCart->getQuote();

        if ($isPreAuth === false || isset($quote) === false) {
            return null;
        }

        $quoteId = $quote->getId();
        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $this->casedataFactory->create();
        $this->casedataResourceModel->load($case, $quoteId, 'quote_id');

        if ($case->isEmpty()) {
            return null;
        }

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
            $this->logger->info("Reason already send");
            return null;
        }

        $openPayData = [];
        $openPayData['gatewayRefusedReason'] = $signifydReason;
        $openPayData['gatewayStatusMessage'] = $e->getDescription();
        $openPayData['gateway'] = 'openpay_cards';

        $case->setEntries("OpenPayRefusedReason", $signifydReason);
        $this->casedataResourceModel->save($case);
        $makeTransactions = $this->transactionsFactory->create();
        $transaction = $makeTransactions($quote, $case->getCheckoutToken(), $openPayData);

        $this->client->postTransactionToSignifyd($transaction, $quote);
        return null;
    }
}
