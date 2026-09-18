<?php

namespace Signifyd\Connect\Model\PreAuth\AdyenOneclick;

use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\JsonSerializer;
use Signifyd\Connect\Model\PreAuth\Base\Handler as BaseHandler;

class Handler extends BaseHandler
{
    /**
     * Key used by the checkout to inform which stored payment method is being used
     */
    public const STORED_PAYMENT_METHOD_ID_KEY = 'storedPaymentMethodId';

    /**
     * Key used by Magento vault to inform which stored card is being used
     */
    public const PUBLIC_HASH_KEY = 'public_hash';

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * @var PaymentTokenManagementInterface
     */
    protected $paymentTokenManagement;

    /**
     * Handler constructor.
     *
     * @param ObjectManagerInterface $objectManager
     * @param JsonSerializer $jsonSerializer
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param Logger $logger
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        JsonSerializer $jsonSerializer,
        PaymentTokenManagementInterface $paymentTokenManagement,
        Logger $logger
    ) {
        $this->objectManager = $objectManager;
        $this->jsonSerializer = $jsonSerializer;
        $this->paymentTokenManagement = $paymentTokenManagement;

        parent::__construct($logger);
    }

    /**
     * Handle Adyen Oneclick
     *
     * @param array $checkoutPaymentDetails
     * @param array $dataArray
     * @param Quote $quote
     * @return array
     */
    public function handle(array $checkoutPaymentDetails, array $dataArray, Quote $quote): array
    {
        $this->logger->info(
            "Collecting Checkout Payment Details using the AdyenOneClick Handler",
            ['entity' => $quote]
        );
        $additionalData = $dataArray['paymentMethod']['additional_data'];
        $storedPaymentMethodId = null;

        if (isset($additionalData['stateData'])) {
            try {
                $stateData = $this->jsonSerializer->unserialize($additionalData['stateData']);
                $storedPaymentMethodId = $stateData['paymentMethod'][self::STORED_PAYMENT_METHOD_ID_KEY] ?? null;
            } catch (\Exception $e) {
                $this->logger->debug(
                    'adyen_oneclick: unable to read the stateData: ' . $e->getMessage(),
                    ['entity' => $quote]
                );
            }
        }

        $checkoutPaymentDetails['cardBin'] = $this->getCardBin($storedPaymentMethodId, $quote);
        $checkoutPaymentDetails['holderName'] = $additionalData['holderName'] ?? null;
        $checkoutPaymentDetails['cardLast4'] = $additionalData['cardLast4'] ?? null;

        if (isset($additionalData['expDate'])) {
            $expDate = explode('-', $additionalData['expDate']);
            $checkoutPaymentDetails['cardExpiryMonth'] = $expDate[0];
            $checkoutPaymentDetails['cardExpiryYear'] = $expDate[1];
        } else {
            $checkoutPaymentDetails['cardExpiryMonth'] = $additionalData['cardExpiryMonth'] ?? null;
            $checkoutPaymentDetails['cardExpiryYear'] = $additionalData['cardExpiryYear'] ?? null;
        }

        return $checkoutPaymentDetails;
    }

    /**
     * Handle Adyen stored cards for checkouts which store the payment data on the quote payment
     *
     * @param array $checkoutPaymentDetails
     * @param Quote $quote
     * @return array
     */
    public function handleQuotePayment(array $checkoutPaymentDetails, Quote $quote): array
    {
        $checkoutPaymentDetails = parent::handleQuotePayment($checkoutPaymentDetails, $quote);

        $payment = $quote->getPayment();

        if (empty($payment)) {
            return $checkoutPaymentDetails;
        }

        $storedPaymentMethodId = $payment->getAdditionalInformation(self::STORED_PAYMENT_METHOD_ID_KEY);
        $vaultCardData = $this->getVaultCardData(
            $payment->getAdditionalInformation(self::PUBLIC_HASH_KEY),
            $quote
        );

        if (empty($storedPaymentMethodId) && isset($vaultCardData['gatewayToken'])) {
            $storedPaymentMethodId = $vaultCardData['gatewayToken'];
        }

        $vaultDataMap = [
            'cardLast4' => 'cardLast4',
            'cardExpiryMonth' => 'cardExpiryMonth',
            'cardExpiryYear' => 'cardExpiryYear'
        ];

        foreach ($vaultDataMap as $detailKey => $vaultKey) {
            if (empty($checkoutPaymentDetails[$detailKey]) && empty($vaultCardData[$vaultKey]) === false) {
                $checkoutPaymentDetails[$detailKey] = $vaultCardData[$vaultKey];
            }
        }

        if (empty($checkoutPaymentDetails['cardBin'])) {
            $checkoutPaymentDetails['cardBin'] = $this->getCardBin($storedPaymentMethodId, $quote);
        }

        return $checkoutPaymentDetails;
    }

    /**
     * Get the card data stored on the Magento vault token
     *
     * @param mixed $publicHash
     * @param Quote $quote
     * @return array
     */
    public function getVaultCardData($publicHash, Quote $quote): array
    {
        if (empty($publicHash) || empty($quote->getCustomerId())) {
            return [];
        }

        try {
            $paymentToken = $this->paymentTokenManagement->getByPublicHash(
                $publicHash,
                $quote->getCustomerId()
            );

            if (isset($paymentToken) === false) {
                return [];
            }

            $cardData = ['gatewayToken' => $paymentToken->getGatewayToken()];
            $tokenDetails = $this->jsonSerializer->unserialize((string)$paymentToken->getTokenDetails());

            if (is_array($tokenDetails) === false) {
                return $cardData;
            }

            if (empty($tokenDetails['maskedCC']) === false) {
                $cardData['cardLast4'] = substr((string)$tokenDetails['maskedCC'], -4);
            }

            // Magento vault stores the expiration date as MM/YYYY
            if (empty($tokenDetails['expirationDate']) === false) {
                $expirationDate = explode('/', (string)$tokenDetails['expirationDate']);

                if (count($expirationDate) === 2) {
                    $cardData['cardExpiryMonth'] = $expirationDate[0];
                    $cardData['cardExpiryYear'] = $expirationDate[1];
                }
            }

            return $cardData;
        } catch (\Exception $e) {
            $this->logger->debug(
                'adyen_oneclick: unable to read the vault token data: ' . $e->getMessage(),
                ['entity' => $quote]
            );

            return [];
        }
    }

    /**
     * Get the bin of a stored card from the Adyen recurring contracts
     *
     * @param mixed $storedPaymentMethodId
     * @param Quote $quote
     * @return string|null
     */
    public function getCardBin($storedPaymentMethodId, Quote $quote)
    {
        if (empty($storedPaymentMethodId)) {
            return null;
        }

        try {
            /** @var \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest */
            $paymentRequest = $this->objectManager->create(\Adyen\Payment\Model\Api\PaymentRequest::class);

            $customerId = $quote->getCustomer()->getId();

            if (empty($customerId)) {
                return null;
            }

            $shopperReference = $customerId < 100
                ? str_pad((string)$customerId, 3, '0', STR_PAD_LEFT)
                : $customerId;

            $contracts = $paymentRequest->getRecurringContractsForShopper(
                $shopperReference,
                $quote->getStoreId()
            );

            return $contracts[$storedPaymentMethodId]['additionalData']['cardBin'] ?? null;
        } catch (\Exception $e) {
            $this->logger->debug(
                'adyen_oneclick: unable to get cardBin from the recurring contracts: ' . $e->getMessage(),
                ['entity' => $quote]
            );

            return null;
        }
    }
}
