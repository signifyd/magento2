<?php

namespace Signifyd\Connect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Signifyd\Connect\Model\Api\CheckoutOrderFactory;
use Signifyd\Connect\Model\Api\Core\Client;
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
     * @var CheckoutOrderFactory
     */
    protected $checkoutOrderFactory;

    /**
     * @var Client
     */
    protected $client;

    /**
     * PreAuth constructor.
     * @param Logger $logger
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
     * @param CheckoutOrderFactory $checkoutOrderFactory
     * @param Client $client
     */
    public function __construct(
        Logger $logger,
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
        ObjectManagerInterface $objectManagerInterface,
        CheckoutOrderFactory $checkoutOrderFactory,
        Client $client
    ) {
        $this->logger = $logger;
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
        $this->checkoutOrderFactory = $checkoutOrderFactory;
        $this->client = $client;
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

            $policyName = $this->configHelper->getPolicyName(
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

            $isPreAuth = $this->configHelper->getIsPreAuth(
                $policyName,
                $paymentMethod,
                $quote->getStore()->getScopeType(),
                $quote->getStoreId()
            );

            if ($isPreAuth === false) {
                /** @var \Signifyd\Connect\Model\Casedata $case */
                $case = $this->casedataFactory->create();
                $this->casedataResourceModel->load($case, $quote->getId(), 'quote_id');

                if ($case->isEmpty() === false && $case->getPolicyName() === Casedata::PRE_AUTH) {
                    $this->casedataResourceModel->delete($case);
                }

                return;
            }

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

                        if ($quote->getCustomer()->getId() < 100) {
                            $shopperReference =
                                str_pad($quote->getCustomer()->getId(), 3, 0, STR_PAD_LEFT);
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

                if (isset($dataArray['paymentMethod']['additional_data']['expDate'])) {
                    $expDate = explode('-', $dataArray['paymentMethod']['additional_data']['expDate']);
                    $checkoutPaymentDetails['cardExpiryMonth'] = $expDate[0];

                    $checkoutPaymentDetails['cardExpiryYear'] = $expDate[1];
                } else {
                    $checkoutPaymentDetails['cardExpiryMonth'] =
                        $dataArray['paymentMethod']['additional_data']['cardExpiryMonth'] ?? null;

                    $checkoutPaymentDetails['cardExpiryYear'] =
                        $dataArray['paymentMethod']['additional_data']['cardExpiryYear'] ?? null;
                }
            }

            $this->logger->info("Creating case for quote {$quote->getId()}");
            $this->addSignifydDataToPayment($quote, $checkoutPaymentDetails);
            $checkoutOrder = $this->checkoutOrderFactory->create();
            $caseFromQuote = $checkoutOrder($quote, $checkoutPaymentDetails, $paymentMethod);
            $caseResponse = $this->client->postCaseFromQuoteToSignifyd($caseFromQuote, $quote);
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

                /** @var \Signifyd\Connect\Model\Casedata $case */
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
                $case->setEntriesText("");

                if (isset($caseResponse->scaEvaluation)) {
                    $case->setEntries(
                        'sca_pre_auth',
                        $caseResponse->scaEvaluation->toJson()
                    );
                }

                $this->casedataResourceModel->save($case);
            }
        } catch (\Exception $e) {
            $caseAction = false;
            $caseResponse = null;
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

        $stopCheckoutProcess = $this->getStopCheckoutProcess($caseResponse, $caseAction);

        if ($stopCheckoutProcess) {
            $policyRejectMessage = $this->scopeConfigInterface->getValue(
                'signifyd/advanced/policy_pre_auth_reject_message',
                ScopeInterface::SCOPE_STORES,
                $quote->getStoreId()
            );

            throw new LocalizedException(__($policyRejectMessage));
        }
    }

    /**
     * @param $caseResponse
     * @param $caseAction
     * @return bool
     */
    public function getStopCheckoutProcess($caseResponse, $caseAction)
    {
        return isset($caseResponse) &&
            is_object($caseResponse) &&
            $caseAction == 'REJECT';
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @param array $checkoutPaymentDetails
     * @return void
     */
    public function addSignifydDataToPayment($quote, $checkoutPaymentDetails)
    {
        if (empty($checkoutPaymentDetails)) {
            return;
        }

        if (isset($checkoutPaymentDetails['cardBin'])) {
            $quote->getPayment()->setData(
                'cc_number',
                $checkoutPaymentDetails['cardBin'] .
                000000 .
                $checkoutPaymentDetails['cardLast4'] ?? 0000
            );
        }

        if (isset($checkoutPaymentDetails['holderName'])) {
            $quote->getPayment()->setCcOwner($checkoutPaymentDetails['holderName']);
        }

        if (isset($checkoutPaymentDetails['cardLast4'])) {
            $quote->getPayment()->setCcLast4($checkoutPaymentDetails['cardLast4']);
        }

        if (isset($checkoutPaymentDetails['cardExpiryMonth'])) {
            $quote->getPayment()->setCcExpMonth($checkoutPaymentDetails['cardExpiryMonth']);
        }

        if (isset($checkoutPaymentDetails['cardExpiryYear'])) {
            $quote->getPayment()->setCcExpYear($checkoutPaymentDetails['cardExpiryYear']);
        }
    }
}
