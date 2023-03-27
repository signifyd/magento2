<?php

namespace Signifyd\Connect\Model;

use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Checkout\Model\Cart as CheckoutCart;

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

    protected $gatewayRefusedReason = null;

    protected $gatewayStatusMessage = null;

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
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        Logger $logger,
        PurchaseHelper $purchaseHelper,
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

        $policyName = $this->purchaseHelper->getPolicyName(
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getId()
        );

        $isPreAuth = $this->purchaseHelper->getIsPreAuth(
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

        $transaction = $this->purchaseHelper->makeCheckoutTransactions(
            $quote,
            $case->getCheckoutToken(),
            $data
        );

        $this->purchaseHelper->postTransactionToSignifyd($transaction, $quote);
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
