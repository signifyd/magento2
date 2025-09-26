<?php

namespace Signifyd\Connect\Plugin\Magento\PaymentServicesPaypal\Gateway\Http;

use Magento\Payment\Gateway\Http\ClientException;
use Magento\PaymentServicesPaypal\Gateway\Http\ServiceClient as PaymentServicesPaypalServiceClient;
use Signifyd\Connect\Model\TransactionIntegration;

class ServiceClient
{
    private const CAPTURE_ERRORS = [
        'INVALID_CURRENCY_CODE' => 'Currency code should be a three-character currency code.',
        // phpcs:disable Magento2.Files.LineLength, Generic.Files.LineLength
        'CANNOT_BE_ZERO_OR_NEGATIVE' => 'Must be greater than zero. If the currency supports decimals, only two decimal place precision is supported.',
        'DECIMAL_PRECISION' => 'The value of the field should not be more than two decimal places.',
        'DECIMALS_NOT_SUPPORTED' => 'Currency does not support decimals.',
        'TRANSACTION_REFUSED' => 'PayPal\'s internal controls prevent authorization from being captured.',
        'AUTHORIZATION_VOIDED' => 'A voided authorization cannot be captured or reauthorized.',
        // phpcs:disable Magento2.Files.LineLength, Generic.Files.LineLength
        'MAX_CAPTURE_COUNT_EXCEEDED' => 'Maximum number of allowable captures has been reached. No additional captures are possible for this authorization. Please contact customer service or your account manager to change the number of captures that be made for a given authorization.',
        // phpcs:disable Magento2.Files.LineLength, Generic.Files.LineLength
        'DUPLICATE_INVOICE_ID' => 'Requested invoice number has been previously captured. Possible duplicate transaction.',
        'AUTH_CAPTURE_CURRENCY_MISMATCH' => 'Currency of capture must be the same as currency of authorization.',
        'AUTHORIZATION_ALREADY_CAPTURED' => 'Authorization has already been captured.',
        'PAYER_CANNOT_PAY' => 'Payer cannot pay for this transaction.',
        'AUTHORIZATION_EXPIRED' => 'An expired authorization cannot be captured.',
        'MAX_CAPTURE_AMOUNT_EXCEEDED' => 'Capture amount exceeds allowable limit.',
        'PAYEE_ACCOUNT_LOCKED_OR_CLOSED' => 'Transaction could not complete because payee account is locked or closed.',
        'PAYER_ACCOUNT_LOCKED_OR_CLOSED' => 'The payer account cannot be used for this transaction.'
    ];

    /**
     * @var TransactionIntegration
     */
    public $transactionIntegration;

    /**
     * ServiceClient constructor.
     *
     * @param TransactionIntegration $transactionIntegration
     */
    public function __construct(
        TransactionIntegration $transactionIntegration
    ) {
        $this->transactionIntegration = $transactionIntegration;
    }

    /**
     * Around place request method.
     *
     * @param PaymentServicesPaypalServiceClient $subject
     * @param callable $proceed
     * @param mixed $transferObject
     * @return void
     * @throws ClientException
     */
    public function aroundPlaceRequest(PaymentServicesPaypalServiceClient $subject, callable $proceed, $transferObject)
    {
        try {
            return $proceed($transferObject);
        } catch (ClientException $e) {
            $signifydReason = null;
            $deniedResponse =  __(
                'Your payment was not successful. '
                . 'Ensure you have entered your details correctly and try again, '
                . 'or try a different payment method. If you have continued problems, '
                . 'contact the issuing bank for your payment method.'
            );
            $processingErrorResponse =  __('Error happened when processing the request. Please try again later.');

            $errorCode = array_search($e->getMessage(), self::CAPTURE_ERRORS);

            if ($errorCode === false) {
                if ($e->getMessage() == $deniedResponse) {
                    $errorCode = "PAYMENT_DENIED";
                } elseif ($e->getMessage() == $processingErrorResponse) {
                    $errorCode = "PROCESSING_ERROR";
                } else {
                    throw new ClientException(__($e->getMessage()));
                }
            }

            switch ($errorCode) {
                case 'PROCESSING_ERROR':
                case 'INVALID_CURRENCY_CODE':
                case 'CANNOT_BE_ZERO_OR_NEGATIVE':
                case 'DECIMAL_PRECISION':
                case 'PAYMENT_DENIED':
                case 'DECIMALS_NOT_SUPPORTED':
                case 'TRANSACTION_REFUSED':
                case 'AUTHORIZATION_VOIDED':
                case 'MAX_CAPTURE_COUNT_EXCEEDED':
                case 'DUPLICATE_INVOICE_ID':
                case 'AUTH_CAPTURE_CURRENCY_MISMATCH':
                case 'AUTHORIZATION_ALREADY_CAPTURED':
                case 'AUTHORIZATION_EXPIRED':
                case 'MAX_CAPTURE_AMOUNT_EXCEEDED':
                case 'PAYEE_ACCOUNT_LOCKED_OR_CLOSED':
                case 'PAYER_ACCOUNT_LOCKED_OR_CLOSED':
                    $signifydReason = 'PROCESSING_ERROR';
                    break;

                case 'PAYER_CANNOT_PAY':
                    $signifydReason = 'INSUFFICIENT_FUNDS';
                    break;
            }

            $this->transactionIntegration->setGatewayRefusedReason($signifydReason);
            $this->transactionIntegration->setGatewayStatusMessage($e->getMessage());
            $this->transactionIntegration->submitToTransactionApi();

            throw new ClientException(__($e->getMessage()));
        }
    }
}
