<?php

namespace Signifyd\Connect\Plugin\Openpay\Cards\Model;

use Signifyd\Connect\Helper\PurchaseHelper;
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
     * @var PurchaseHelper
     */
    protected $purchaseHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CheckoutCart
     */
    protected $checkoutCart;

    /**
     * CheckoutPaymentsDetailsHandler constructor.
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param Logger $logger
     * @param PurchaseHelper $purchaseHelper
     * @param StoreManagerInterface $storeManager
     * @param CheckoutCart $checkoutCart
     */
    public function __construct(
        CasedataFactory       $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        Logger                $logger,
        PurchaseHelper        $purchaseHelper,
        StoreManagerInterface $storeManager,
        CheckoutCart $checkoutCart
    ) {
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->logger = $logger;
        $this->purchaseHelper = $purchaseHelper;
        $this->storeManager = $storeManager;
        $this->checkoutCart = $checkoutCart;
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
        $policyName = $this->purchaseHelper->getPolicyName(
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getId()
        );

        $isPreAuth = $this->purchaseHelper->getIsPreAuth($policyName, 'openpay_cards');

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
            $this->logger->info("Reason already send");
            return null;
        }

        $openPayData = [];
        $openPayData['gatewayRefusedReason'] = $signifydReason;
        $openPayData['gatewayStatusMessage'] = $e->getDescription();
        $openPayData['gateway'] = 'openpay_cards';

        $case->setEntries("OpenPayRefusedReason", $signifydReason);
        $this->casedataResourceModel->save($case);

        $transaction = $this->purchaseHelper->makeCheckoutTransactions(
            $quote,
            $case->getCheckoutToken(),
            $openPayData
        );

        $this->purchaseHelper->postTransactionToSignifyd($transaction, $quote);
        return null;
    }
}
