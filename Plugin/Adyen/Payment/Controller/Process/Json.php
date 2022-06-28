<?php

namespace Signifyd\Connect\Plugin\Adyen\Payment\Controller\Process;

use Adyen\Payment\Controller\Process\Json as AdyenJson;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\Casedata;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;

class Json
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
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var QuoteResourceModel
     */
    protected $quoteResourceModel;

    /**
     * CheckoutPaymentsDetailsHandler constructor.
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param Logger $logger
     * @param PurchaseHelper $purchaseHelper
     * @param StoreManagerInterface $storeManager
     * @param QuoteFactory $quoteFactory
     * @param QuoteResourceModel $quoteResourceModel
     */
    public function __construct(
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        Logger $logger,
        PurchaseHelper $purchaseHelper,
        StoreManagerInterface $storeManager,
        QuoteFactory $quoteFactory,
        QuoteResourceModel $quoteResourceModel
    ) {
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->logger = $logger;
        $this->purchaseHelper = $purchaseHelper;
        $this->storeManager = $storeManager;
        $this->quoteFactory = $quoteFactory;
        $this->quoteResourceModel = $quoteResourceModel;
    }

    public function beforeExecute(AdyenJson $subject)
    {
        $policyName = $this->purchaseHelper->getPolicyName(
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getId()
        );

        $isPreAuth = $this->purchaseHelper->getIsPreAuth($policyName, 'adyen_cc');
        $notificationItems = json_decode(file_get_contents('php://input'), true);

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

            /** @var $case \Signifyd\Connect\Model\Casedata */
            $case = $this->casedataFactory->create();
            $this->casedataResourceModel->load($case, $orderIncrement, 'order_increment');

            if ($case->isEmpty() === false && $isSuccess === "false") {
                if ($case->getEntries('AdyenRefusedReason') == $reason) {
                    return null;
                }

                $this->logger->info(
                    "collecting Adyen pre-authorization transaction data for case " . $case->getCode()
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

                $transaction = $this->purchaseHelper->makeCheckoutTransactions(
                    $quote,
                    $case->getCheckoutToken(),
                    $adyenData
                );

                $this->purchaseHelper->postTransactionToSignifyd($transaction, $quote);
            }
        }
    }
}
