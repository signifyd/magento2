<?php

namespace Signifyd\Connect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Helper\PurchaseHelper;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\CasedataFactory;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\ObjectManagerInterface;

class PreAuth implements ObserverInterface
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var PurchaseHelper
     */
    protected $purchaseHelper;

    /**
     * @var ResponseFactory
     */
    protected $responseFactory;

    /**
     * @var UrlInterface
     */
    protected $url;

    /**
     * @var RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var ResponseInterface
     */
    protected $responseInterface;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var RequestHttp
     */
    protected $requestHttp;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManagerInterface;

    /**
     * PreAuth constructor.
     * @param Logger $logger
     * @param PurchaseHelper $purchaseHelper
     * @param CartRepositoryInterface $quoteRepository
     * @param ResponseFactory $responseFactory
     * @param UrlInterface $url
     * @param RedirectFactory $resultRedirectFactory
     * @param ResponseInterface $responseInterface
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param RequestHttp $requestHttp
     * @param JsonSerializer $jsonSerializer
     * @param ConfigHelper $configHelper
     * @param ObjectManagerInterface $objectManagerInterface
     */
    public function __construct(
        Logger $logger,
        PurchaseHelper $purchaseHelper,
        CartRepositoryInterface $quoteRepository,
        ResponseFactory $responseFactory,
        UrlInterface $url,
        RedirectFactory $resultRedirectFactory,
        ResponseInterface $responseInterface,
        ScopeConfigInterface $scopeConfigInterface,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        RequestHttp $requestHttp,
        JsonSerializer $jsonSerializer,
        ConfigHelper $configHelper,
        ObjectManagerInterface $objectManagerInterface
    ) {
        $this->logger = $logger;
        $this->purchaseHelper = $purchaseHelper;
        $this->quoteRepository = $quoteRepository;
        $this->responseFactory = $responseFactory;
        $this->url = $url;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->responseInterface = $responseInterface;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->requestHttp = $requestHttp;
        $this->jsonSerializer = $jsonSerializer;
        $this->configHelper = $configHelper;
        $this->objectManagerInterface = $objectManagerInterface;
    }

    public function execute(Observer $observer)
    {
        try {
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $observer->getEvent()->getQuote();

            if ($this->configHelper->isEnabled($quote) == false) {
                return;
            }

            $this->logger->info("policy validation");

            $policyName = $this->purchaseHelper->getPolicyName(
                $quote->getStore()->getScopeType(),
                $quote->getStoreId()
            );

            $paymentMethod = null;
            $data = $this->requestHttp->getContent();

            if (empty($data) === false) {
                $dataArray = $this->jsonSerializer->unserialize($data);
            } else {
                $dataArray = [];
            }

            if (isset($dataArray['paymentMethod']) &&
                isset($dataArray['paymentMethod']['method'])
            ) {
                $paymentMethod = $dataArray['paymentMethod']['method'];
            }

            $isPreAuth = $this->purchaseHelper->getIsPreAuth($policyName, $paymentMethod);

            if ($isPreAuth === false) {
                /** @var $case \Signifyd\Connect\Model\Casedata */
                $case = $this->casedataFactory->create();
                $this->casedataResourceModel->load($case, $quote->getId(), 'quote_id');

                if ($case->isEmpty() === false) {
                    $this->casedataResourceModel->delete($case);
                }

                return;
            }

            $policyRejectMessage = $this->scopeConfigInterface->getValue(
                'signifyd/advanced/policy_pre_auth_reject_message',
                ScopeInterface::SCOPE_STORES,
                $quote->getStoreId()
            );

            $checkoutPaymentDetails = [];

            if (isset($dataArray['paymentMethod']) &&
                    isset($dataArray['paymentMethod']['additional_data'])
            ) {
                if ($paymentMethod == 'adyen_oneclick' &&
                    isset($dataArray['paymentMethod']['additional_data']['stateData'])
                ) {
                    try {
                        $stateData = $this->jsonSerializer
                            ->unserialize($dataArray['paymentMethod']['additional_data']['stateData']);

                        /** @var \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest */
                        $paymentRequest = $this->objectManagerInterface->create(
                            \Adyen\Payment\Model\Api\PaymentRequest::class
                        );

                        if ($quote->getCustomer()->getId() < 10) {
                            $shopperReference = '00' . $quote->getCustomer()->getId();
                        } elseif ($quote->getCustomer()->getId() >= 10 && $quote->getCustomer()->getId() <= 100) {
                            $shopperReference = '0' . $quote->getCustomer()->getId();
                        } else {
                            $shopperReference = $quote->getCustomer()->getId();
                        }

                        $contracts = $paymentRequest->getRecurringContractsForShopper(
                            $shopperReference,
                            $quote->getStoreId()
                        );

                        if (isset($stateData['paymentMethod']) &&
                            isset($stateData['paymentMethod']['storedPaymentMethodId'])
                        ) {
                            $storedPaymentMethodId = $stateData['paymentMethod']['storedPaymentMethodId'];

                            $checkoutPaymentDetails['cardBin'] =
                                $contracts[$storedPaymentMethodId]['additionalData']['cardBin'] ?? null;
                        } else {
                            $checkoutPaymentDetails['cardBin'] = null;
                        }
                    } catch (\Exception $e) {
                        $checkoutPaymentDetails['cardBin'] = null;
                    }
                } else {
                    $checkoutPaymentDetails['cardBin'] =
                        $dataArray['paymentMethod']['additional_data']['cardBin'] ?? null;
                }

                $checkoutPaymentDetails['holderName'] =
                    $dataArray['paymentMethod']['additional_data']['holderName'] ?? null;

                $checkoutPaymentDetails['cardLast4'] =
                    $dataArray['paymentMethod']['additional_data']['cardLast4'] ?? null;

                $checkoutPaymentDetails['cardExpiryMonth'] =
                    $dataArray['paymentMethod']['additional_data']['cardExpiryMonth'] ?? null;

                $checkoutPaymentDetails['cardExpiryYear'] =
                    $dataArray['paymentMethod']['additional_data']['cardExpiryYear'] ?? null;
            }

            $this->logger->info("Creating case for quote {$quote->getId()}");
            $caseFromQuote = $this->purchaseHelper->processQuoteData($quote, $checkoutPaymentDetails, $paymentMethod);
            $caseResponse = $this->purchaseHelper->postCaseFromQuoteToSignifyd($caseFromQuote, $quote);
            $validActions = ['ACCEPT', 'REJECT', 'HOLD', 'PENDING'];
            $caseAction = false;

            if (isset($caseResponse->decision)) {
                if (isset($caseResponse->decision->checkpointAction)) {
                    $caseAction = $caseResponse->decision->checkpointAction;
                }
            }

            if ($caseAction !== false && in_array($caseAction, $validActions)) {
                if ($caseAction == 'ACCEPT' || $caseAction == 'REJECT') {
                    $magentoStatus = Casedata::PRE_AUTH;
                } else {
                    $magentoStatus = Casedata::IN_REVIEW_STATUS;
                }

                /** @var $case \Signifyd\Connect\Model\Casedata */
                $case = $this->casedataFactory->create();
                $this->casedataResourceModel->load($case, $quote->getId(), 'quote_id');
                $case->setCode($caseResponse->signifydId);
                $case->setScore(floor($caseResponse->decision->score));
                $case->setGuarantee($caseAction);
                $case->setCreated(date('Y-m-d H:i:s', time()));
                $case->setUpdated();
                $case->setMagentoStatus($magentoStatus);
                $case->setPolicyName(Casedata::PRE_AUTH);
                $case->setCheckoutToken($caseFromQuote['checkoutId']);
                $case->setQuoteId($quote->getId());
                $case->setOrderIncrement($quote->getReservedOrderId());
                $entries = $case->getEntriesText();

                if (isset($entries) === false) {
                    $case->setEntriesText("");
                }

                if (isset($caseResponse->scaEvaluation)) {
                    $case->setEntries(
                        'tra_pre_auth',
                        $caseResponse->scaEvaluation->toJson()
                    );
                }

                $this->casedataResourceModel->save($case);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        $enabledConfig = $this->scopeConfigInterface->getValue(
            'signifyd/general/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $quote->getStoreId()
        );

        if ($enabledConfig == 'passive') {
            return;
        }

        if (isset($caseResponse) &&
            is_object($caseResponse) &&
            $caseAction == 'REJECT') {
            throw new LocalizedException(__($policyRejectMessage));
        }
    }
}
