<?php

namespace Signifyd\Connect\Model\Payment\Base;

/**
 * Maps a gateway decline code to the gatewayErrorCode enum accepted by the Signifyd
 * Transaction API.
 *
 * The codes below are the Stripe decline codes, which are also the ones the other
 * gateways integrated by this module are normalized to.
 */
class GatewayErrorCodeMapper
{
    /**
     * @var array
     */
    public const DECLINE_CODE_MAP = [
        'call_issuer' => 'CALL_ISSUER',
        'expired_card' => 'EXPIRED_CARD',
        'fraudulent' => 'FRAUD_DECLINE',
        'incorrect_number' => 'INCORRECT_NUMBER',
        'incorrect_cvc' => 'INCORRECT_CVC',
        'incorrect_zip' => 'INCORRECT_ZIP',
        'insufficient_funds' => 'INSUFFICIENT_FUNDS',
        'invalid_cvc' => 'INVALID_CVC',
        'invalid_expiry_month' => 'INVALID_EXPIRY_DATE',
        'invalid_expiry_year' => 'INVALID_EXPIRY_DATE',
        'invalid_number' => 'INVALID_NUMBER',
        'pickup_card' => 'PICK_UP_CARD',
        'processing_error' => 'PROCESSING_ERROR',
        'restricted_card' => 'RESTRICTED_CARD',
        'stolen_card' => 'STOLEN_CARD',
        'testmode_decline' => 'TEST_CARD_DECLINE',
        'authentication_required' => 'CARD_DECLINED',
        'approve_with_id' => 'CARD_DECLINED',
        'card_not_supported' => 'CARD_DECLINED',
        'card_velocity_exceeded' => 'CARD_DECLINED',
        'currency_not_supported' => 'CARD_DECLINED',
        'do_not_honor' => 'CARD_DECLINED',
        'do_not_try_again' => 'CARD_DECLINED',
        'duplicate_transaction' => 'CARD_DECLINED',
        'generic_decline' => 'CARD_DECLINED',
        'incorrect_pin' => 'CARD_DECLINED',
        'invalid_account' => 'CARD_DECLINED',
        'invalid_amount' => 'CARD_DECLINED',
        'invalid_pin' => 'CARD_DECLINED',
        'issuer_not_available' => 'CARD_DECLINED',
        'lost_card' => 'CARD_DECLINED',
        'merchant_blacklist' => 'CARD_DECLINED',
        'new_account_information_available' => 'CARD_DECLINED',
        'no_action_taken' => 'CARD_DECLINED',
        'not_permitted' => 'CARD_DECLINED',
        'offline_pin_required' => 'CARD_DECLINED',
        'online_or_offline_pin_required' => 'CARD_DECLINED',
        'pin_try_exceeded' => 'CARD_DECLINED',
        'reenter_transaction' => 'CARD_DECLINED',
        'revocation_of_all_authorizations' => 'CARD_DECLINED',
        'revocation_of_authorization' => 'CARD_DECLINED',
        'security_violation' => 'CARD_DECLINED',
        'service_not_allowed' => 'CARD_DECLINED',
        'stop_payment_order' => 'CARD_DECLINED',
        'transaction_not_allowed' => 'CARD_DECLINED',
        'try_again_later' => 'CARD_DECLINED',
        'withdrawal_count_limit_exceeded' => 'CARD_DECLINED',
    ];

    /**
     * Error codes for gateway failures that are not a card decline.
     *
     * @var array
     */
    public const ERROR_CODE_MAP = [
        'payment_intent_authentication_failure' => 'CARD_DECLINED',
        'payment_intent_payment_attempt_failed' => 'PROCESSING_ERROR',
        'card_declined' => 'CARD_DECLINED',
    ];

    /**
     * Translates a gateway decline code into a Signifyd gatewayErrorCode.
     *
     * @param mixed $declineCode
     * @param mixed $errorCode
     * @return string|null
     */
    public function __invoke($declineCode, $errorCode = null)
    {
        if (is_string($declineCode) && isset(self::DECLINE_CODE_MAP[$declineCode])) {
            return self::DECLINE_CODE_MAP[$declineCode];
        }

        if (is_string($errorCode) && isset(self::ERROR_CODE_MAP[$errorCode])) {
            return self::ERROR_CODE_MAP[$errorCode];
        }

        return null;
    }
}
