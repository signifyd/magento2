<?php

namespace Signifyd\Connect\Plugin\MercadoPago\Model;

use MercadoPago\Core\Model\Core as MercadoPagoCore;
use Signifyd\Connect\Model\TransactionIntegration;

class Core
{
    /**
     * @var TransactionIntegration
     */
    public $transactionIntegration;

    /**
     * @param TransactionIntegration $transactionIntegration
     */
    public function __construct(
        TransactionIntegration $transactionIntegration
    ) {
        $this->transactionIntegration = $transactionIntegration;
    }

    /**
     * @param MercadoPagoCore $subject
     * @param $response
     * @return mixed
     */
    public function afterPostPaymentV1(MercadoPagoCore $subject, $response)
    {
        if (isset($response['status']) &&
            ((int) $response['status'] == 200 ||
            (int) $response['status'] == 201)
        ) {
            if (isset($response['response']['status']) &&
                $response['response']['status'] == 'rejected'
            ) {
                switch ($response['response']['status_detail']) {
                    case 'cc_rejected_bad_filled_other':
                        $signifydReason = 'CARD_DECLINED';
                        $signifydStatusMessage = 'incorrect card details';
                        break;

                    case 'cc_rejected_blacklist':
                        $signifydReason = 'CARD_DECLINED';
                        $signifydStatusMessage = 'the card is on a black list for theft/complaints/fraud';
                        break;

                    case 'cc_rejected_call_for_authorize':
                        $signifydReason = 'CARD_DECLINED';
                        $signifydStatusMessage = 'the means of payment requires prior ' .
                            'authorization of the amount of the operation';
                        break;

                    case 'cc_rejected_card_disabled':
                        $signifydReason = 'CARD_DECLINED';
                        $signifydStatusMessage = 'the card is inactive';
                        break;

                    case 'cc_rejected_duplicated_payment':
                        $signifydReason = 'CARD_DECLINED';
                        $signifydStatusMessage = 'duplicated payment';
                        break;

                    case 'cc_rejected_high_risk':
                        $signifydReason = 'CARD_DECLINED';
                        $signifydStatusMessage = 'high risk';
                        break;

                    case 'cc_rejected_invalid_installments':
                        $signifydReason = 'CARD_DECLINED';
                        $signifydStatusMessage = 'invalid number of installments';
                        break;

                    case 'cc_rejected_max_attempts':
                        $signifydReason = 'CARD_DECLINED';
                        $signifydStatusMessage = 'exceeded maximum number of attempts';
                        break;

                    case 'cc_rejected_other_reason':
                        $signifydReason = 'CARD_DECLINED';
                        $signifydStatusMessage = 'generic error';
                        break;

                    case 'cc_rejected_bad_filled_date':
                        $signifydReason = 'EXPIRED_CARD';
                        $signifydStatusMessage = 'incorrect expiration date';
                        break;

                    case 'cc_rejected_bad_filled_security_code':
                        $signifydReason = 'INCORRECT_CVC';
                        $signifydStatusMessage = 'incorrect CVV';
                        break;

                    case 'cc_rejected_insufficient_amount':
                        $signifydReason = 'INSUFFICIENT_FUNDS';
                        $signifydStatusMessage = 'insufficient amount';
                        break;
                }

                if (isset($signifydReason) === false) {
                    return $response;
                }

                if (isset($signifydStatusMessage)) {
                    $this->transactionIntegration->setGatewayStatusMessage($signifydStatusMessage);
                }

                $this->transactionIntegration->setGatewayRefusedReason($signifydReason);
                $this->transactionIntegration->submitToTransactionApi();
            }
        }

        return $response;
    }
}
