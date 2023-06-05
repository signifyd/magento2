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

        if (isset($additionalData['threeDAuthenticated']) === false) {
            return;
        }

        if ($additionalData['threeDAuthenticated'] === 'false' &&
            isset($additionalData['scaExemptionRequested']) === false
        ) {
            return;
        }

        $content = $this->httpRequest->getContent();
        $contentArray = $this->jsonSerializer->unserialize($content);

        if (isset($contentArray['payload'])) {
            $payload = $this->jsonSerializer->unserialize($contentArray['payload']);

            if (isset($payload['orderId'])) {
                $orderId = $payload['orderId'];
            } else {
                return;
            }
        } elseif (isset($contentArray['orderId'])) {
            $orderId = $contentArray['orderId'];
        } else {
            return;
        }

        $order = $this->orderRepository->get($orderId);
        $quoteId = $order->getQuoteId();

        $threeDsData = [];

        if (isset($additionalData['eci']) && $additionalData['eci'] != "N/A") {
            $threeDsData['eci'] = $additionalData['eci'];
        }

        if (isset($additionalData['cavv']) && $additionalData['cavv'] != "N/A") {
            $threeDsData['cavv'] = $additionalData['cavv'];
        }

        if (isset($additionalData['threeDSVersion']) && $additionalData['threeDSVersion'] != "N/A") {
            $threeDsData['version'] = $additionalData['threeDSVersion'];
        }

        if (isset($additionalData['threeDAuthenticatedResponse']) &&
            $additionalData['threeDAuthenticatedResponse'] != "N/A"
        ) {
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

        if (isset($additionalData['dsTransID']) && $additionalData['dsTransID'] != "N/A") {
            $threeDsData['dsTransId'] = $additionalData['dsTransID'];
        }

        if (isset($additionalData['scaExemptionRequested']) && $additionalData['scaExemptionRequested'] != "N/A") {
            $signifydScaExemption = null;

            switch ($additionalData['scaExemptionRequested']) {
                case 'lowValue':
                    $signifydScaExemption = 'LOW_VALUE';
                    break;

                case 'secureCorporate':
                    $signifydScaExemption = 'SECURE_CORPORATE';
                    break;

                case 'trustedBeneficiary':
                    $signifydScaExemption = 'TRUSTED_BENEFICIARY';
                    break;

                case 'transactionRiskAnalysis':
                    $signifydScaExemption = 'TRA';
                    break;
            }

            $threeDsData['exemptionIndicator'] = $signifydScaExemption;

            //Version is a mandatory field for Signifyd, as in the case of exemption Adyen does not return a value,
            // it is necessary to fill it in
            $threeDsData['version'] = $threeDsData['version'] ?? "N/A";
        }

        $this->threeDsIntegration->setThreeDsData($threeDsData, $quoteId);

        return [$resultCode, $action, $additionalData];
    }
}
