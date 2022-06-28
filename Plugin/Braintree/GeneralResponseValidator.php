<?php

namespace Signifyd\Connect\Plugin\Braintree;

use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\Casedata;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;

class GeneralResponseValidator
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

    protected $subjectReader;

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

    public function beforeValidate($subject, array $validationSubject)
    {
        $this->logger->info("Braintree pre-authorization transaction validator");

        $policyName = $this->purchaseHelper->getPolicyName(
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getId()
        );

        $isPreAuth = $this->purchaseHelper->getIsPreAuth($policyName, 'braintree');

        if ($isPreAuth === false) {
            return null;
        }

        $response = $this->subjectReader->readResponseObject($validationSubject);

        if ($response->success == 1) {
            return;
        }

        $responseBraintree = $response->jsonSerialize()['transaction']->jsonSerialize() ?? [];

        if (isset($responseBraintree['orderId']) &&
            isset($responseBraintree['cvvResponseCode']) &&
            isset($responseBraintree['avsPostalCodeResponseCode']) &&
            isset($responseBraintree['avsStreetAddressResponseCode'])
        ) {
            $orderIncrement = $responseBraintree['orderId'];

            if ($responseBraintree['cvvResponseCode'] != 'M') {
                $reason = 'cvv_' . $responseBraintree['cvvResponseCode'];
            } elseif ($responseBraintree['avsPostalCodeResponseCode'] != 'M') {
                $reason = 'avs_postal_' . $responseBraintree['avsPostalCodeResponseCode'];
            } elseif ($responseBraintree['avsStreetAddressResponseCode'] != 'M') {
                $reason = 'avs_street_' . $responseBraintree['avsStreetAddressResponseCode'];
            } else {
                $reason = 'default';
            }

            /** @var $case \Signifyd\Connect\Model\Casedata */
            $case = $this->casedataFactory->create();
            $this->casedataResourceModel->load($case, $orderIncrement, 'order_increment');

            if ($case->isEmpty() === false) {
                switch ($reason) {
                    case "cvv_N":
                        $signifydReason = "INCORRECT_CVC";
                        break;

                    case "cvv_U":
                        $signifydReason = "INVALID_CVC";
                        break;

                    case "avs_postal_N":
                    case "avs_postal_U":
                        $signifydReason = "INCORRECT_ZIP";
                        break;

                    case "avs_street_N":
                    case "avs_street_U":
                        $signifydReason = "INCORRECT_ADDRESS";
                        break;

                    default:
                        $signifydReason = "CARD_DECLINED";
                        break;
                }

                if ($case->getEntries('BraintreeRefusedReason') == $signifydReason) {
                    return null;
                }

                $this->logger->info(
                    "collecting Braintree pre-authorization transaction data for case " . $case->getCode()
                );

                $branitreeData = [];
                $branitreeData['gatewayRefusedReason'] = $signifydReason;
                $branitreeData['gateway'] = 'braintree';

                $case->setEntries("BraintreeRefusedReason", $signifydReason);
                $this->casedataResourceModel->save($case);

                if (isset($responseBraintree['creditCard'])) {
                    $branitreeData['cardLast4'] = $responseBraintree['creditCard']['last4'] ?? null;
                    $branitreeData['cardExpiryMonth'] = $responseBraintree['creditCard']['expirationMonth'] ?? null;
                    $branitreeData['cardExpiryYear'] = $responseBraintree['creditCard']['expirationYear'] ?? null;
                }

                $quote = $this->quoteFactory->create();
                $this->quoteResourceModel->load($quote, $case->getQuoteId());

                $transaction = $this->purchaseHelper->makeCheckoutTransactions(
                    $quote,
                    $case->getCheckoutToken(),
                    $branitreeData
                );
                $this->purchaseHelper->postTransactionToSignifyd($transaction, $quote);
            }
        }
    }
}
