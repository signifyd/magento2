<?php

namespace Signifyd\Connect\Plugin\Rootways\Authorizecim\Gateway\Validator;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Psr\Log\LoggerInterface;
use Rootways\Authorizecim\Gateway\Validator\ResponseValidator as AuthorizecimResponseValidator;
use Signifyd\Connect\Model\TransactionIntegration;

class ResponseValidatorPlugin
{
    /**
     * @var TransactionIntegration
     */
    public $transactionIntegration;

    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * ResponseValidatorPlugin construct.
     *
     * @param TransactionIntegration $transactionIntegration
     * @param LoggerInterface $logger
     */
    public function __construct(
        TransactionIntegration $transactionIntegration,
        LoggerInterface $logger
    ) {
        $this->transactionIntegration = $transactionIntegration;
        $this->logger = $logger;
    }

    /**
     * Before validate method.
     *
     * @param AuthorizecimResponseValidator $subject
     * @param array $validationSubject
     * @return array[]
     */
    public function beforeValidate(AuthorizecimResponseValidator $subject, array $validationSubject)
    {
        try {
            $response = SubjectReader::readResponse($validationSubject);
            $errorCode = [];
            $errorMessages = [];

            if (isset($response['transactionResponse']['errors']['error']['errorText'])) {
                $errorCode[] = $response['transactionResponse']['errors']['error']['errorCode'];
                $errorMessages[] = __($response['transactionResponse']['errors']['error']['errorText']);
                $this->submitErrorToSignifyd($errorCode, $errorMessages);
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        } catch (\Error $err) {
            $this->logger->critical($err);
        }

        return [$validationSubject];
    }

    /**
     * Submit error to signifyd method.
     *
     * @param array $errorCode
     * @param array $errorMessages
     * @return void
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Signifyd\Core\Exceptions\ApiException
     * @throws \Signifyd\Core\Exceptions\InvalidClassException
     */
    protected function submitErrorToSignifyd($errorCode, $errorMessages)
    {
        $authorizeErrorCode = $errorCode[0] ?? '';
        $authorizeErrorMessages = $errorMessages[0]->getText() ?? '';

        //Mapping the error according to Signifyd doc
        switch ($authorizeErrorCode) {
            case 6:
            case 37:
            case 315:
                $signifydReason = 'INVALID_NUMBER';
                break;

            case 7:
            case 8:
            case 316:
            case 317:
                $signifydReason = 'INVALID_EXPIRY_DATE';
                break;

            case 19:
            case 20:
            case 21:
            case 22:
            case 23:
            case 25:
            case 26:
            case 35:
            case 57:
            case 58:
            case 59:
            case 60:
            case 61:
            case 62:
            case 63:
            case 120:
            case 121:
            case 122:
            case 153:
            case 170:
            case 171:
            case 172:
            case 173:
            case 180:
            case 181:
            case 192:
            case 261:
                $signifydReason = 'PROCESSING_ERROR';
                break;

            default:
                $signifydReason = 'CARD_DECLINED';
                break;

        }

        $this->transactionIntegration->setGatewayRefusedReason($signifydReason);
        $this->transactionIntegration->setGatewayStatusMessage($authorizeErrorMessages);
        $this->transactionIntegration->submitToTransactionApi();
    }
}
