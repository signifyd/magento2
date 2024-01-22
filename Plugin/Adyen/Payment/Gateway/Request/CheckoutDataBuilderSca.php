<?php

namespace Signifyd\Connect\Plugin\Adyen\Payment\Gateway\Request;

use Adyen\Payment\Gateway\Request\CheckoutDataBuilder as AdyenCheckoutDataBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\ScaPreAuth\ScaEvaluation;

class CheckoutDataBuilderSca
{
    /**
     * @var QuoteResourceModel
     */
    protected $quoteResourceModel;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var int
     */
    protected $quoteId;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var ScaEvaluation
     */
    protected $scaEvaluation;

    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * CheckoutDataBuilder constructor.
     * @param QuoteResourceModel $quoteResourceModel
     * @param QuoteFactory $quoteFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param ConfigHelper $configHelper
     * @param Logger $logger
     * @param StoreManagerInterface $storeManagerInterface
     * @param ScaEvaluation $scaEvaluation
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     */
    public function __construct(
        QuoteResourceModel $quoteResourceModel,
        QuoteFactory $quoteFactory,
        ScopeConfigInterface $scopeConfig,
        ConfigHelper $configHelper,
        Logger $logger,
        StoreManagerInterface $storeManagerInterface,
        ScaEvaluation $scaEvaluation,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel
    ) {
        $this->quoteResourceModel = $quoteResourceModel;
        $this->quoteFactory = $quoteFactory;
        $this->scopeConfig = $scopeConfig;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->scaEvaluation = $scaEvaluation;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
    }

    public function beforeBuild(AdyenCheckoutDataBuilder $subject, array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $this->quoteId = $payment->getOrder()->getQuoteId();
    }

    public function afterBuild(AdyenCheckoutDataBuilder $subject, $request)
    {
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->quoteFactory->create();
        $this->quoteResourceModel->load($quote, $this->quoteId);

        $scaEvaluation = $this->scaEvaluation->getScaEvaluation($quote);

        if ($scaEvaluation instanceof \Signifyd\Models\ScaEvaluation === false) {
            return $request;
        }

        $executeThreeD = null;
        $scaExemption = null;

        switch ($scaEvaluation->outcome) {
            case 'REQUEST_EXEMPTION':
                $this->logger->info("Signifyd's recommendation is to request exemption", ['entity' => $quote]);

                $placement = $scaEvaluation->exemptionDetails->placement;
                $scaExemption = 'tra';

                if ($placement === 'AUTHENTICATION') {
                    $executeThreeD = 'always';
                } elseif ($placement === 'AUTHORIZATION') {
                    $executeThreeD = 'never';
                }
                break;

            case 'REQUEST_EXCLUSION':
            case 'DELEGATE_TO_PSP':
                $recommendation = $scaEvaluation->outcome == 'DELEGATE_TO_PSP' ?
                    'no exemption/exclusion identified' : 'exclusion';

                $this->logger->info("Signifyd's recommendation is {$recommendation}", ['entity' => $quote]);

                $executeThreeD = '';
                $scaExemption = '';
                break;

            case 'SOFT_DECLINE':
                $this->logger->info("Processor responds back with a soft decline", ['entity' => $quote]);

                $executeThreeD = 'always';
                $scaExemption = '';
                break;
        }

        if (isset($executeThreeD) && isset($scaExemption)) {
            if (\Adyen\Client::API_CHECKOUT_VERSION === "v69") {
                $request['body']['authenticationData']['attemptAuthentication'] = $executeThreeD;
            } else {
                switch ($executeThreeD) {
                    case 'always':
                        $executeThreeD = 'True';
                        break;

                    case 'never';
                        $executeThreeD = 'False';
                        break;
                }

                $request['body']['additionalData']['executeThreeD'] = $executeThreeD;
            }

            $request['body']['additionalData']['scaExemption'] = $scaExemption;
        }

        return $request;
    }
}
