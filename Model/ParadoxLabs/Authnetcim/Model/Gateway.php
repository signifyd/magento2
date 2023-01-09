<?php

namespace Signifyd\Connect\Model\ParadoxLabs\Authnetcim\Model;

use ParadoxLabs\Authnetcim\Model\Gateway as ParadoxLabsGateway;
use Signifyd\Connect\Model\TransactionIntegration;

class Gateway extends ParadoxLabsGateway
{
    /**
     * @var TransactionIntegration
     */
    protected $transactionIntegration;

    /**
     * CheckoutPaymentsDetailsHandler constructor.
     * @param TransactionIntegration $transactionIntegration
     */
    public function __construct(
        TransactionIntegration $transactionIntegration,
        \ParadoxLabs\TokenBase\Helper\Data $helper,
        \ParadoxLabs\TokenBase\Model\Gateway\Xml $xml,
        \ParadoxLabs\TokenBase\Model\Gateway\ResponseFactory $responseFactory,
        \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory,
        \Magento\Framework\Module\Dir $moduleDir,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->transactionIntegration = $transactionIntegration;
        parent::__construct(
            $helper,
            $xml,
            $responseFactory,
            $httpClientFactory,
            $moduleDir,
            $registry,
            $data
        );
    }

    protected function handleTransactionError()
    {
        if (empty($this->lastResponse) === false &&
            isset($this->lastResponse['transactionResponse']) &&
            isset($this->lastResponse['transactionResponse']['responseCode']) &&
            isset($this->lastResponse['transactionResponse']['errors']) &&
            isset($this->lastResponse['transactionResponse']['errors']['error']) &&
            isset($this->lastResponse['transactionResponse']['errors']['error']['errorCode']) &&
            $this->lastResponse['transactionResponse']['responseCode'] == 2
        ) {
            switch ($this->lastResponse['transactionResponse']['errors']['error']['errorCode']) {
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

            if (isset($this->lastResponse['transactionResponse']['errors']['error']['errorText'])) {
                $this->transactionIntegration->setGatewayStatusMessage(
                    $this->lastResponse['transactionResponse']['errors']['error']['errorText']
                );
            }

            $this->transactionIntegration->setGatewayRefusedReason($signifydReason);
            $this->transactionIntegration->submitToTransactionApi();
        }

        parent::handleTransactionError();
    }
}
