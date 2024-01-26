<?php

namespace Signifyd\Connect\Plugin\Magento\PaymentServicesPaypal\Gateway\Http;

use Magento\Payment\Gateway\Http\ClientException;
use Magento\PaymentServicesPaypal\Gateway\Http\ServiceClient as PaymentServicesPaypalServiceClient;
use Signifyd\Connect\Model\TransactionIntegration;

class ServiceClient
{
    /**
     * @var TransactionIntegration
     */
    public $transactionIntegration;

    /**
     * ServiceClient constructor.
     * @param TransactionIntegration $transactionIntegration
     */
    public function __construct(
        TransactionIntegration $transactionIntegration
    ) {
        $this->transactionIntegration = $transactionIntegration;
    }

    /**
     * @param PaymentServicesPaypalServiceClient $subject
     * @param callable $proceed
     * @param $transferObject
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

            $errorCode = array_search($e->getMessage(), PaymentServicesPaypalServiceClient::CAPTURE_ERRORS);

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
