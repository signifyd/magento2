<?php

namespace Signifyd\Connect\Plugin\CyberSource\Gateway\Http\Client;

use CyberSource\SecureAcceptance\Gateway\Http\Client\SOAPClient as CyberSOAPClient;
use Signifyd\Connect\Model\Registry;
use Signifyd\Connect\Model\TransactionIntegration;

class SOAPClient
{
    /**
     * @var TransactionIntegration
     */
    protected $transactionIntegration;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * CheckoutPaymentsDetailsHandler constructor.
     * @param TransactionIntegration $transactionIntegration
     * @param Registry $registry
     */
    public function __construct(
        TransactionIntegration $transactionIntegration,
        Registry $registry
    ) {
        $this->transactionIntegration = $transactionIntegration;
        $this->registry = $registry;
    }

    /**
     * @param CyberSOAPClient $subject
     * @param $response
     * @return mixed
     */
    public function afterPlaceRequest(CyberSOAPClient $subject, $response)
    {
        if (is_array($response) === false) {
            return $response;
        }

        if (isset($response['decision']) === false || isset($response['reasonCode']) === false) {
            return $response;
        }

        if ($response['decision'] != 'REJECT') {
            $this->registry->setData('signifyd_payment_data', $response);
            return $response;
        }

        $signifydReason = null;
        $signifydMessage = null;

        switch ($response['reasonCode']) {
            case '101':
                $signifydReason = 'CARD_DECLINED';
                $signifydMessage = 'Declined - The request is missing one or more fields';
                break;

            case '102':
                $signifydReason = 'EXPIRED_CARD';
                $signifydMessage = 'Declined - One or more fields in the request contains invalid data.';
                break;

            case '104':
                $signifydReason = 'EXPIRED_CARD';
                $signifydMessage = 'Declined - The merchantReferenceCode sent with this authorization request matches' .
                    ' the merchantReferenceCode of another authorization request that you sent in the last 15 minutes.';
                break;

            case '150':
                $signifydReason = 'PROCESSING_ERROR';
                $signifydMessage = 'Error - General system failure.';
                break;

            case '151':
                $signifydReason = 'PROCESSING_ERROR';
                $signifydMessage = 'Error - The request was received but there was a server timeout.' .
                    ' This error does not include timeouts between the client and the server.';
                break;

            case '152':
                $signifydReason = 'PROCESSING_ERROR';
                $signifydMessage = 'Error: The request was received, but a service did not finish running in time.';
                break;

            case '154':
                $signifydReason = 'CARD_DECLINED';
                $signifydMessage = 'Bad MaC key.';
                break;

            case '200':
            case '230':
            case '400':
            case '520':
                $signifydReason = 'CARD_DECLINED';
                $signifydMessage = 'Soft Decline.';
                break;

            case '201':
                $signifydReason = 'CARD_DECLINED';
                $signifydMessage = 'Decline - The issuing bank has questions about the request.';
                break;

            case '202':
                $signifydReason = 'EXPIRED_CARD';
                $signifydMessage = 'Decline - Expired card. You might also receive this if the expiration date you' .
                    ' provided does not match the date the issuing bank has on file.';
                break;

            case '203':
                $signifydReason = 'CARD_DECLINED';
                $signifydMessage = 'Decline - General decline of the card. No other information provided by' .
                    ' the issuing bank.';
                break;

            case '204':
                $signifydReason = 'INSUFFICIENT_FUNDS';
                $signifydMessage = 'Decline - Insufficient funds in the account.';
                break;

            case '205':
                $signifydReason = 'STOLEN_CARD';
                $signifydMessage = 'Decline - Stolen or lost card.';
                break;

            case '207':
                $signifydReason = 'CARD_DECLINED';
                $signifydMessage = 'Decline - Issuing bank unavailable.';
                break;

            case '208':
                $signifydReason = 'CARD_DECLINED';
                $signifydMessage = 'Decline - Inactive card or card not authorized for card-not-present transactions.';
                break;

            case '209':
            case '211':
                $signifydReason = 'INVALID_CVC';
                $signifydMessage = 'Decline - card verification number (CVN) did not match.';
                break;

            case '210':
                $signifydReason = 'INSUFFICIENT_FUNDS';
                $signifydMessage = 'Decline - The card has reached the credit limit.';
                break;

            case '220':
            case '221':
            case '222':
            case '231':
            case '232':
            case '233':
            case '234':
            case '235':
            case '237':
            case '238':
            case '239':
            case '241':
            case '242':
            case '243':
            case '246':
            case '247':
            case '251':
            case '254':
            case '268':
            case '450':
            case '475':
            case '476':
            case '478':
            case '480':
            case '481':
            case '490':
            case '491':
            case '700':
            case '701':
            case '702':
            case '703':
                $signifydReason = 'CARD_DECLINED';
                $signifydMessage = 'Decline - Generic Decline.';
                break;

            case '236':
            case '248':
            case '250':
                $signifydReason = 'PROCESSING_ERROR';
                $signifydMessage = 'Decline - Processor failure.';
                break;

            case '240':
                $signifydReason = 'INVALID_NUMBER';
                $signifydMessage = 'Decline - The card type sent is invalid or does not correlate with the credit' .
                    ' card number.';
                break;

            case '451':
            case '452':
            case '453':
            case '454':
            case '455':
            case '456':
            case '457':
            case '458':
            case '459':
            case '460':
            case '461':
                $signifydReason = 'INCORRECT_ADDRESS';
                $signifydMessage = 'Invalid address.';
                break;
        }

        if (isset($signifydReason) === false || isset($signifydMessage) === false) {
            return $response;
        }

        $this->transactionIntegration->setGatewayStatusMessage($signifydMessage);
        $this->transactionIntegration->setGatewayRefusedReason($signifydReason);
        $this->transactionIntegration->submitToTransactionApi();

        return $response;
    }
}
