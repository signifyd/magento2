<?php

namespace Signifyd\Connect\Plugin\Braintree\Gateway\Request;

use Magento\Checkout\Model\Session;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use PayPal\Braintree\Gateway\Request\PaymentDataBuilder as BraintreePaymentDataBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\ScaPreAuth\ScaEvaluation;
use Signifyd\Connect\Model\ScaPreAuth\ScaEvaluationConfig;

class PaymentDataBuilder
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ScaEvaluation
     */
    protected $scaEvaluation;

    /**
     * @var ScaEvaluationConfig
     */
    protected $scaEvaluationConfig;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var QuoteResourceModel
     */
    protected $quoteResourceModel;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @param StoreManagerInterface $storeManager
     * @param Logger $logger
     * @param ScaEvaluation $scaEvaluation
     * @param ScaEvaluationConfig $scaEvaluationConfig
     * @param Session $session
     * @param QuoteResourceModel $quoteResourceModel
     * @param QuoteFactory $quoteFactory
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Logger $logger,
        ScaEvaluation $scaEvaluation,
        ScaEvaluationConfig $scaEvaluationConfig,
        Session $session,
        QuoteResourceModel $quoteResourceModel,
        QuoteFactory $quoteFactory
    ) {
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->scaEvaluation = $scaEvaluation;
        $this->scaEvaluationConfig = $scaEvaluationConfig;
        $this->session = $session;
        $this->quoteResourceModel = $quoteResourceModel;
        $this->quoteFactory = $quoteFactory;
    }

    public function afterBuild(BraintreePaymentDataBuilder $subject, $result, $buildSubject)
    {
        $quoteId = $this->session->getQuoteId();

        if (empty($quoteId)) {
            return $result;
        }

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->quoteFactory->create();
        $this->quoteResourceModel->load($quote, $quoteId);

        if (empty($quote)) {
            return $result;
        }

        $scaEvaluation = $this->scaEvaluation->getScaEvaluation($quote);

        if ($scaEvaluation instanceof \Signifyd\Models\ScaEvaluation === false) {
            return $result;
        }

        $scaExemption = null;

        switch ($scaEvaluation->outcome) {
            case 'REQUEST_EXEMPTION':
                $this->logger->info("Signifyd's recommendation is to request exemption");

                //TODO: SET VALUE
                $scaExemption = 'trusted_beneficiary';
                break;

            case 'REQUEST_EXCLUSION':
            case 'DELEGATE_TO_PSP':

                //TODO: SET VALUE
                $scaExemption = '';
                break;

            case 'SOFT_DECLINE':
                $this->logger->info("Processor responds back with a soft decline");

                //TODO: SET VALUE
                $scaExemption = '';
                break;
        }

        if (isset($scaExemption)) {
            $result['scaExemption'] = $scaExemption;
        }

        return $result;
    }
}