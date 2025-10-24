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
use Magento\Quote\Model\Quote;
use Signifyd\Connect\Model\Api\CheckoutOrderFactory;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\CasedataFactory;
use Magento\Framework\App\Request\Http as RequestHttp;
use Signifyd\Connect\Model\JsonSerializer;
use Magento\Framework\ObjectManagerInterface;
use Signifyd\Connect\Model\Api\Recipient;

class PreAuth implements ObserverInterface
{
    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var CartRepositoryInterface
     */
    public $quoteRepository;

    /**
     * @var ResponseFactory
     */
    public $responseFactory;

    /**
     * @var UrlInterface
     */
    public $url;

    /**
     * @var RedirectFactory
     */
    public $resultRedirectFactory;

    /**
     * @var ResponseInterface
     */
    public $responseInterface;

    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfigInterface;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    public $casedataResourceModel;

    /**
     * @var RequestHttp
     */
    public $requestHttp;

    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var ObjectManagerInterface
     */
    public $objectManagerInterface;

    /**
     * @var CheckoutOrderFactory
     */
    public $checkoutOrderFactory;

    /**
     * @var Client
     */
    public $client;

    /**
     * @var Recipient
     */
    public $recipient;

    /**
     * PreAuth constructor.
     *
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
     * @param Recipient $recipient
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
        Client $client,
        Recipient $recipient
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
        $this->recipient = $recipient;
    }

    /**
     * Execute method.
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $observer->getEvent()->getQuote();

            if ($this->configHelper->isEnabled($quote) == false) {
                return;
            }

            $this->logger->info("policy validation", ['entity' => $quote]);

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
            } else {
                $payment = $quote->getPayment();
                $paymentMethod = $payment->getMethod();
            }

            if (isset($paymentMethod) && $this->configHelper->isPaymentRestricted($paymentMethod)) {
                $message = 'Case creation with payment ' . $paymentMethod . ' is restricted';
                $this->logger->debug($message, ['entity' => $quote]);
                return;
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

            $customerGroupId = $quote->getCustomerGroupId();

            if ($this->configHelper->isCustomerGroupRestricted($customerGroupId)) {
                $message = 'Case creation with customer group id ' . $customerGroupId . ' is restricted';
                $this->logger->debug($message, ['entity' => $quote]);
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

                    if ($paymentMethod === 'rootways_authorizecim_option') {
                        $checkoutPaymentDetails = $this->mappingForAuthnetRootwaysCim(
                            $checkoutPaymentDetails,
                            $dataArray
                        );
                    }

                    if ($paymentMethod === 'authnetcim') {
                        $checkoutPaymentDetails = $this->mappingForAuthnet($checkoutPaymentDetails, $dataArray);
                    }
                }
            } elseif (isset($payment)) {
                $checkoutPaymentDetails['cardBin'] = $payment->getAdditionalInformation('cardBin');
                $checkoutPaymentDetails['cardExpiryMonth'] = $payment->getAdditionalInformation('cardExpiryMonth');
                $checkoutPaymentDetails['cardExpiryYear'] = $payment->getAdditionalInformation('cardExpiryYear');
                $checkoutPaymentDetails['cardLast4'] = $payment->getAdditionalInformation('cardLast4');
                $checkoutPaymentDetails['holderName'] = $payment->getAdditionalInformation('holderName');
            }

            $this->logger->info("Creating case for quote {$quote->getId()}", ['entity' => $quote]);
            $this->addSignifydDataToPayment($quote, $checkoutPaymentDetails, $paymentMethod);
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

                $recipient = ($this->recipient)($quote);
                $recipientJson = $this->jsonSerializer->serialize($recipient, $quote);
                $hashToValidateReroute = sha1($recipientJson);
                $case->setEntries('hash', $hashToValidateReroute);

                $this->casedataResourceModel->save($case);
            }
        } catch (\Exception $e) {
            $caseAction = false;
            $caseResponse = null;
            $this->logger->error($e->getMessage(), ['entity' => $quote]);
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
     * Get stop checkout process method.
     *
     * @param mixed $caseResponse
     * @param mixed $caseAction
     * @return bool
     */
    public function getStopCheckoutProcess($caseResponse, $caseAction)
    {
        return isset($caseResponse) &&
            is_object($caseResponse) &&
            $caseAction == 'REJECT';
    }

    /**
     * Add data to payment
     *
     * @param Quote $quote
     * @param array $checkoutPaymentDetails
     * @param mixed $paymentMethod
     * @return void
     */
    public function addSignifydDataToPayment($quote, $checkoutPaymentDetails, $paymentMethod)
    {
        if (empty($checkoutPaymentDetails)) {
            return;
        }

        $ccNumber = $quote->getPayment()->getData('cc_number');

        if (!isset($ccNumber)
            && isset($checkoutPaymentDetails['cardBin'])
            && isset($checkoutPaymentDetails['cardLast4'])
            && $paymentMethod !== 'authnetcim'
        ) {
            $quote->getPayment()->setData(
                'cc_number',
                ($checkoutPaymentDetails['cardBin'] ?? '000000') .
                '000000' .
                ($checkoutPaymentDetails['cardLast4'] ?? '0000')
            );
        }

        if (isset($checkoutPaymentDetails['cardBin'])) {
            $quote->getPayment()->setAdditionalInformation('card_bin', $checkoutPaymentDetails['cardBin']);
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

    /**
     * Mapping for authnet rootways cim method.
     *
     * @param array $checkoutPaymentDetails
     * @param array $dataArray
     * @return mixed
     */
    public function mappingForAuthnetRootwaysCim($checkoutPaymentDetails, $dataArray)
    {
        $additionalData = $dataArray['paymentMethod']['additional_data'];

        $checkoutPaymentDetails['cardExpiryMonth'] = $additionalData['cc_exp_month'] ?? null;
        $checkoutPaymentDetails['cardExpiryYear'] = $additionalData['cc_exp_year'] ?? null;

        $cc_number = $additionalData['cc_number'] ?? null;
        if ($cc_number) {
            $checkoutPaymentDetails['cardLast4'] = substr($cc_number, -4);
            $checkoutPaymentDetails['cardBin'] = substr($cc_number, 0, 6);
        } else {
            $checkoutPaymentDetails['cardLast4'] = null;
            $checkoutPaymentDetails['cardBin'] = $additionalData['card_bin'] ?? null;
        }

        return $checkoutPaymentDetails;
    }

    /**
     * Mapping for authnet method.
     *
     * @param array $checkoutPaymentDetails
     * @param array $dataArray
     * @return mixed
     */
    public function mappingForAuthnet($checkoutPaymentDetails, $dataArray)
    {
        $additionalData = $dataArray['paymentMethod']['additional_data'];

        if (isset($dataArray['paymentMethod']['additional_data']['card_id'])) {
            /** @var \ParadoxLabs\TokenBase\Model\ResourceModel\Card\Collection $cardCollection */
            $cardCollection = $this->objectManagerInterface->create(
                \ParadoxLabs\TokenBase\Model\ResourceModel\Card\CollectionFactory::class
            )->create()
                ->addFieldToFilter('hash', ['eq' => $dataArray['paymentMethod']['additional_data']['card_id']]);

            $card = $cardCollection->getFirstItem();

            if (empty($card->getData('additional')) === false) {
                $additionalData = $this->jsonSerializer->unserialize($card->getData('additional'));
            }
        }

        $checkoutPaymentDetails['cardExpiryMonth'] = $additionalData['cc_exp_month'] ?? null;

        $checkoutPaymentDetails['cardExpiryYear'] = $additionalData['cc_exp_year'] ?? null;

        $checkoutPaymentDetails['cardLast4'] = $additionalData['cc_last4'] ?? null;

        $checkoutPaymentDetails['cardBin'] = $additionalData['cc_bin'] ?? null;

        return $checkoutPaymentDetails;
    }
}
