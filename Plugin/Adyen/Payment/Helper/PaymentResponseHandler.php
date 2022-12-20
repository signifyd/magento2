<?php

namespace Signifyd\Connect\Plugin\Adyen\Payment\Helper;

use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Sales\Api\OrderRepositoryInterface;
use Signifyd\Connect\Model\ThreeDsIntegration;
use Adyen\Payment\Helper\PaymentResponseHandler as AdyenPaymentResponseHandler;
use Magento\Framework\App\Request\Http as HttpRequest;

class PaymentResponseHandler
{
    /**
     * @var ThreeDsIntegration
     */
    protected $threeDsIntegration;

    /**
     * @var HttpRequest
     */
    protected $httpRequest;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param ThreeDsIntegration $threeDsIntegration
     * @param HttpRequest $httpRequest
     * @param JsonSerializer $jsonSerializer
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        ThreeDsIntegration $threeDsIntegration,
        HttpRequest $httpRequest,
        JsonSerializer $jsonSerializer,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->threeDsIntegration = $threeDsIntegration;
        $this->httpRequest = $httpRequest;
        $this->jsonSerializer = $jsonSerializer;
        $this->orderRepository = $orderRepository;
    }

    public function beforeFormatPaymentResponse(
        AdyenPaymentResponseHandler $subject,
        $resultCode,
        $action = null,
        $additionalData = null
    ) {
        if (isset($additionalData) === false || is_array($additionalData) === false) {
            return;
        }

        if (isset($additionalData['threeDAuthenticated']) === false || $additionalData['threeDAuthenticated'] === 'false') {
            return;
        }

        $content = $this->httpRequest->getContent();
        $contentArray = $this->jsonSerializer->unserialize($content);

        if (isset($contentArray['payload'])) {
            $payload = $this->jsonSerializer->unserialize($contentArray['payload']);

            if (isset($payload['orderId'])) {
                $order = $this->orderRepository->get($payload['orderId']);
                $quoteId = $order->getQuoteId();
            }
        } else {
            return;
        }

        $threeDsData = [];

        if (isset($additionalData['eci'])) {
            $threeDsData['eci'] = $additionalData['eci'];
        }

        if (isset($additionalData['cavv'])) {
            $threeDsData['cavv'] = $additionalData['cavv'];
        }

        if (isset($additionalData['threeDSVersion'])) {
            $threeDsData['version'] = $additionalData['threeDSVersion'];
        }

        if (isset($additionalData['threeDAuthenticatedResponse'])) {
            switch ($additionalData['threeDAuthenticatedResponse']) {
                case 'Y':
                    $threeDAuthenticatedResponse = 'AUTHENTICATION_SUCCESS';
                    break;

                case 'U':
                    $threeDAuthenticatedResponse = 'AUTHENTICATION_UNAVAILABLE';
                    break;

                case 'A':
                    $threeDAuthenticatedResponse = 'AUTHENTICATION_ATTEMPTED';
                    break;

                default:
                    $threeDAuthenticatedResponse = 'AUTHENTICATION_FAILED';
                    break;
            }

            $threeDsData['transStatus'] = $threeDAuthenticatedResponse;
        }

        if (isset($additionalData['dsTransID'])) {
            $threeDsData['dsTransId'] = $additionalData['dsTransID'];
        }

        $this->threeDsIntegration->setThreeDsData($threeDsData, $quoteId);

        return [$resultCode, $action, $additionalData];
    }
}
