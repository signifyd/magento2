<?php

namespace Signifyd\Connect\Plugin\Magento\Sales\Model\Service;

use Signifyd\Connect\Model\TransactionIntegration;
use Magento\Sales\Model\Service\OrderService as MagentoOrderService;
use Magento\Sales\Api\Data\OrderInterface;

class OrderService
{
    /**
     * @var TransactionIntegration
     */
    public $transactionIntegration;

    /**
     * OrderService constructor.
     *
     * @param TransactionIntegration $transactionIntegration
     */
    public function __construct(
        TransactionIntegration $transactionIntegration
    ) {
        $this->transactionIntegration = $transactionIntegration;
    }

    /**
     * Around Place method responsible for mapping the error returned by the card.
     *
     * @param MagentoOrderService $subject
     * @param \Closure $proceed
     * @param OrderInterface $order
     * @return mixed
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Signifyd\Core\Exceptions\ApiException
     * @throws \Signifyd\Core\Exceptions\InvalidClassException
     */
    public function aroundPlace(MagentoOrderService $subject, \Closure $proceed, OrderInterface $order)
    {
        try {
            return $proceed($order);
        } catch (\Exception $e) {
            try {
                $declineCode = $e->getError()?->decline_code;
            } catch (\Exception $e) {
                $declineCode = null;
            } catch (\Error $e) {
                $declineCode = null;
            }

            if (isset($declineCode) === false) {
                throw $e;
            }

            switch ($declineCode) {
                case 'call_issuer':
                    $signifydReason = 'CALL_ISSUER';
                    break;

                case 'expired_card':
                    $signifydReason = 'EXPIRED_CARD';
                    break;

                case 'fraudulent':
                    $signifydReason = 'FRAUD_DECLINE';
                    break;

                case 'incorrect_number':
                    $signifydReason = 'INCORRECT_NUMBER';
                    break;

                case 'incorrect_cvc':
                    $signifydReason = 'INCORRECT_CVC';
                    break;

                case 'incorrect_zip':
                    $signifydReason = 'INCORRECT_ZIP';
                    break;

                case 'insufficient_funds':
                    $signifydReason = 'INSUFFICIENT_FUNDS';
                    break;

                case 'invalid_cvc':
                    $signifydReason = 'INVALID_CVC';
                    break;

                case 'invalid_expiry_month':
                case 'invalid_expiry_year':
                    $signifydReason = 'INVALID_EXPIRY_DATE';
                    break;

                case 'invalid_number':
                    $signifydReason = 'INVALID_NUMBER';
                    break;

                case 'pickup_card':
                    $signifydReason = 'PICK_UP_CARD';
                    break;

                case 'processing_error':
                    $signifydReason = 'PROCESSING_ERROR';
                    break;

                case 'restricted_card':
                    $signifydReason = 'RESTRICTED_CARD';
                    break;

                case 'stolen_card':
                    $signifydReason = 'STOLEN_CARD';
                    break;

                case 'testmode_decline':
                    $signifydReason = 'TEST_CARD_DECLINE';
                    break;

                case 'authentication_required':
                case 'approve_with_id':
                case 'card_not_supported':
                case 'card_velocity_exceeded':
                case 'currency_not_supported':
                case 'do_not_honor':
                case 'do_not_try_again':
                case 'duplicate_transaction':
                case 'generic_decline':
                case 'incorrect_pin':
                case 'invalid_account':
                case 'invalid_amount':
                case 'invalid_pin':
                case 'issuer_not_available':
                case 'lost_card':
                case 'merchant_blacklist':
                case 'new_account_information_available':
                case 'no_action_taken':
                case 'not_permitted':
                case 'offline_pin_required':
                case 'online_or_offline_pin_required':
                case 'pin_try_exceeded':
                case 'reenter_transaction':
                case 'revocation_of_all_authorizations':
                case 'revocation_of_authorization':
                case 'security_violation':
                case 'service_not_allowed':
                case 'stop_payment_order':
                case 'transaction_not_allowed':
                case 'try_again_later':
                case 'withdrawal_count_limit_exceeded':
                    $signifydReason = 'CARD_DECLINED';
                    break;
            }

            if (isset($signifydReason) === false) {
                throw $e;
            }

            if (isset($e->getError()?->message)) {
                $this->transactionIntegration->setGatewayStatusMessage($e->getError()?->message);
            }

            $this->transactionIntegration->setGatewayRefusedReason($signifydReason);
            $this->transactionIntegration->submitToTransactionApi();

            throw $e;
        }
    }
}
