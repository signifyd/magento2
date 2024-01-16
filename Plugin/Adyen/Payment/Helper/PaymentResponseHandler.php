<?php

namespace Signifyd\Connect\Plugin\Adyen\Payment\Helper;

use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;
use Signifyd\Connect\Model\ThreeDsIntegration;
use Adyen\Payment\Helper\PaymentResponseHandler as AdyenPaymentResponseHandler;
use Magento\Framework\App\Request\Http as HttpRequest;
use Signifyd\Connect\Model\CasedataFactory;

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
     * @var SignifydOrderResourceModel
     */
    protected $signifydOrderResourceModel;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @param ThreeDsIntegration $threeDsIntegration
     * @param HttpRequest $httpRequest
     * @param JsonSerializer $jsonSerializer
     * @param OrderRepositoryInterface $orderRepository
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     * @param Logger $logger
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param OrderHelper $orderHelper
     */
    public function __construct(
        ThreeDsIntegration $threeDsIntegration,
        HttpRequest $httpRequest,
        JsonSerializer $jsonSerializer,
        OrderRepositoryInterface $orderRepository,
        SignifydOrderResourceModel $signifydOrderResourceModel,
        Logger $logger,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        OrderHelper $orderHelper
    ) {
        $this->threeDsIntegration = $threeDsIntegration;
        $this->httpRequest = $httpRequest;
        $this->jsonSerializer = $jsonSerializer;
        $this->orderRepository = $orderRepository;
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;
        $this->logger = $logger;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->orderHelper = $orderHelper;
    }

    public function beforeFormatPaymentResponse(
        AdyenPaymentResponseHandler $subject,
        $resultCode,
        $action = null,
        $additionalData = null
    ) {
        if (isset($additionalData) === false || is_array($additionalData) === false) {
            return [$resultCode, $action, $additionalData];
        }

        if (isset($additionalData['threeDAuthenticated']) === false) {
            return [$resultCode, $action, $additionalData];
        }

        if ($additionalData['threeDAuthenticated'] === 'false' &&
            isset($additionalData['scaExemptionRequested']) === false
        ) {
            return [$resultCode, $action, $additionalData];
        }

        $content = $this->httpRequest->getContent();
        $contentArray = $this->jsonSerializer->unserialize($content);

        if (isset($contentArray['payload'])) {
            $payload = $this->jsonSerializer->unserialize($contentArray['payload']);

            if (isset($payload['orderId'])) {
                $orderId = $payload['orderId'];
            } else {
                return [$resultCode, $action, $additionalData];
            }
        } elseif (isset($contentArray['orderId'])) {
            $orderId = $contentArray['orderId'];
        } else {
            return [$resultCode, $action, $additionalData];
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

    public function beforeHandlePaymentResponse(
        AdyenPaymentResponseHandler $subject,
        $paymentsResponse,
        $payment,
        $order = null
    ) {
        if (empty($paymentsResponse) || null === $order) {
            return [$paymentsResponse, $payment, $order];
        }

        try {
            if (isset($paymentsResponse['resultCode']) && $paymentsResponse['resultCode'] === $subject::REFUSED) {
                $orderId = $order->getId();
                $case = $this->casedataFactory->create();
                $this->casedataResourceModel->load($case, $orderId, 'order_id');

                if ($case->isEmpty()) {
                    return [$paymentsResponse, $payment, $order];
                }

                if ($case->getData('magento_status') === Casedata::ASYNC_WAIT && empty($case->getData('code'))) {
                    $case->setEntries('async_action', 'delete');
                    $this->casedataResourceModel->save($case);
                }

                if ($order->canUnhold()) {
                    try {
                        $order->unhold();
                        $this->signifydOrderResourceModel->save($order);
                        $this->logger->info(
                            "Unhold order {$order->getIncrementId()} before adyen tries to cancel",
                            ['entity' => $order]
                        );

                        $this->orderHelper->addCommentToStatusHistory(
                            $order,
                            "Signifyd: unhold order before adyen tries to cancel"
                        );
                    } catch (\Exception $e) {
                        $this->logger->debug($e->__toString(), ['entity' => $order]);

                        $this->orderHelper->addCommentToStatusHistory(
                            $order,
                            "Signifyd: order cannot be unholded, {$e->getMessage()}"
                        );
                    }
                }
            }
        } catch (\Exception $ex) {
            $context = [];

            if (isset($order) && $order instanceof Order) {
                $context['entity'] = $order;
            }

            $this->logger->error($ex->getMessage(), $context);
        } catch (\Error $ex) {
            $context = [];

            if (isset($order) && $order instanceof Order) {
                $context['entity'] = $order;
            }

            $this->logger->error($ex->getMessage(), $context);
        }

        return [$paymentsResponse, $payment, $order];
    }
}
