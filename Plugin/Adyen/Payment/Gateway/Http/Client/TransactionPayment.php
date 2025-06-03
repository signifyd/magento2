<?php

namespace Signifyd\Connect\Plugin\Adyen\Payment\Gateway\Http\Client;

use Adyen\Payment\Gateway\Http\Client\TransactionPayment as AdyenTransactionPayment;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\ScaPreAuth\ScaEvaluation;
use Signifyd\Connect\Model\ScaPreAuth\ScaEvaluationConfig;

class TransactionPayment
{
    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var ScaEvaluation
     */
    public $scaEvaluation;

    /**
     * @var ScaEvaluationConfig
     */
    public $scaEvaluationConfig;

    /**
     * TransactionPayment construct.
     *
     * @param StoreManagerInterface $storeManager
     * @param Logger $logger
     * @param ScaEvaluation $scaEvaluation
     * @param ScaEvaluationConfig $scaEvaluationConfig
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Logger $logger,
        ScaEvaluation $scaEvaluation,
        ScaEvaluationConfig $scaEvaluationConfig
    ) {
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->scaEvaluation = $scaEvaluation;
        $this->scaEvaluationConfig = $scaEvaluationConfig;
    }

    /**
     * After place request method.
     *
     * @param AdyenTransactionPayment $subject
     * @param mixed $response
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function afterPlaceRequest(AdyenTransactionPayment $subject, $response)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $isScaEnabled = $this->scaEvaluationConfig->isScaEnabled($storeId, 'adyen_cc');

        if ($isScaEnabled === false) {
            return $response;
        }

        if (isset($response['refusalReasonCode']) && (int)$response['refusalReasonCode'] === 38) {
            $this->logger->info("Registering adyen soft decline response");
            $this->scaEvaluation->setIsSoftDecline(true);
        } else {
            $this->scaEvaluation->setIsSoftDecline(false);
        }

        return $response;
    }
}
