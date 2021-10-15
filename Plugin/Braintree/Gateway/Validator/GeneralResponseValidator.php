<?php

namespace Signifyd\Connect\Plugin\Braintree\Gateway\Validator;

use Braintree\Result\Error;
use Braintree\Result\Successful;
use Magento\Braintree\Gateway\SubjectReader;
use Magento\Braintree\Gateway\Validator\GeneralResponseValidator as BraintreeGeneralResponseValidator;
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

    /**
     * @var SubjectReader
     */
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
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        Logger $logger,
        PurchaseHelper $purchaseHelper,
        StoreManagerInterface $storeManager,
        QuoteFactory $quoteFactory,
        QuoteResourceModel $quoteResourceModel,
        SubjectReader $subjectReader
    ) {
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->logger = $logger;
        $this->purchaseHelper = $purchaseHelper;
        $this->storeManager = $storeManager;
        $this->quoteFactory = $quoteFactory;
        $this->quoteResourceModel = $quoteResourceModel;
        $this->subjectReader = $subjectReader;
    }

    public function beforeValidate(BraintreeGeneralResponseValidator $subject, array $validationSubject)
    {
        $policyName = $this->purchaseHelper->getPolicyName(
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getId()
        );

        $isPreAuth = $this->purchaseHelper->getIsPreAuth($policyName, 'braintree');

        if ($isPreAuth === false)  {
            return null;
        }

        /** @var Successful|Error $response */
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
                $this->logger->info(
                    "collecting Braintree pre-authorization transaction data for case " . $case->getCode()
                );

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

                $transaction = [];
                $transaction['transactions'] = $this->purchaseHelper->makeTransactionsFromQuote($quote, $branitreeData);
                $transaction['checkoutToken'] = $case->getCheckoutToken();
                $this->purchaseHelper->postTransactionToSignifyd($transaction, $quote);
            }
        }
    }
}
