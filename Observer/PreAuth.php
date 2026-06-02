<?php

namespace Signifyd\Connect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
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
use Signifyd\Connect\Model\CasedataFactory;
use Magento\Framework\App\Request\Http as RequestHttp;
use Signifyd\Connect\Model\JsonSerializer;
use Signifyd\Connect\Model\Api\Recipient;
use Signifyd\Connect\Model\PreAuth\CheckoutPaymentDetailsMapperInterface;
use Signifyd\Connect\Model\Registry;

class PreAuth implements ObserverInterface
{
    /**
     * @var CasedataRepositoryInterface
     */
    public $casedataRepository;

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
     * @var Registry
     */
    public $registry;

    /**
     * @var CheckoutPaymentDetailsMapperInterface
     */
    public $defaultCheckoutPaymentDetailsHandler;

    public $checkoutPaymentDetailsHandlers;

    /**
     * PreAuth constructor.
     *
     * @param CasedataRepositoryInterface $casedataRepository
     * @param Logger $logger
     * @param CartRepositoryInterface $quoteRepository
     * @param ResponseFactory $responseFactory
     * @param UrlInterface $url
     * @param RedirectFactory $resultRedirectFactory
     * @param ResponseInterface $responseInterface
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param CasedataFactory $casedataFactory
     * @param RequestHttp $requestHttp
     * @param JsonSerializer $jsonSerializer
     * @param ConfigHelper $configHelper
     * @param ObjectManagerInterface $objectManagerInterface
     * @param CheckoutOrderFactory $checkoutOrderFactory
     * @param Client $client
     * @param Recipient $recipient
     * @param Registry $registry
     * @param CheckoutPaymentDetailsMapperInterface $defaultCheckoutPaymentDetailsHandler
     * @param array $checkoutPaymentDetailsHandlers
     */
    public function __construct(
        CasedataRepositoryInterface $casedataRepository,
        Logger $logger,
        CartRepositoryInterface $quoteRepository,
        ResponseFactory $responseFactory,
        UrlInterface $url,
        RedirectFactory $resultRedirectFactory,
        ResponseInterface $responseInterface,
        ScopeConfigInterface $scopeConfigInterface,
        CasedataFactory $casedataFactory,
        RequestHttp $requestHttp,
        JsonSerializer $jsonSerializer,
        ConfigHelper $configHelper,
        CheckoutOrderFactory $checkoutOrderFactory,
        Client $client,
        Recipient $recipient,
        Registry $registry,
        CheckoutPaymentDetailsMapperInterface $defaultCheckoutPaymentDetailsHandler,
        array $checkoutPaymentDetailsHandlers = []
    ) {
        $this->casedataRepository = $casedataRepository;
        $this->logger = $logger;
        $this->quoteRepository = $quoteRepository;
        $this->responseFactory = $responseFactory;
        $this->url = $url;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->responseInterface = $responseInterface;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->casedataFactory = $casedataFactory;
        $this->requestHttp = $requestHttp;
        $this->jsonSerializer = $jsonSerializer;
        $this->configHelper = $configHelper;
        $this->checkoutOrderFactory = $checkoutOrderFactory;
        $this->client = $client;
        $this->recipient = $recipient;
        $this->registry = $registry;
        $this->defaultCheckoutPaymentDetailsHandler = $defaultCheckoutPaymentDetailsHandler;
        $this->checkoutPaymentDetailsHandlers = $checkoutPaymentDetailsHandlers;
    }

    /**
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

            try {
                $dataArray = $this->jsonSerializer->unserialize($data);
            } catch (\InvalidArgumentException $e) {
                $dataArray = [];
            }

            if (isset($dataArray['paymentMethod']) && isset($dataArray['paymentMethod']['method'])) {
                $paymentMethod = $dataArray['paymentMethod']['method'];
            } else {
                $payment = $quote->getPayment();
                $paymentMethod = $payment->getMethod();
            }

            if (isset($paymentMethod) === false) {
                $paymentMethod = $this->registry->getData('paymentMethod');
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
                $case = $this->casedataRepository->getByQuoteId($quote->getId());

                if ($case->isEmpty() === false && $case->getPolicyName() === Casedata::PRE_AUTH) {
                    $this->casedataRepository->delete($case);
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

            if (isset($dataArray['paymentMethod']) && isset($dataArray['paymentMethod']['additional_data'])) {
                $handler = $this->checkoutPaymentDetailsHandlers[$paymentMethod]
                    ?? $this->defaultCheckoutPaymentDetailsHandler;
                $checkoutPaymentDetails = $handler->handle($checkoutPaymentDetails, $dataArray, $quote);
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
                $case = $this->casedataRepository->getByQuoteId($quote->getId());
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

                $recipient = ($this->recipient)($quote);
                $recipientJson = $this->jsonSerializer->serialize($recipient, $quote);
                $hashToValidateReroute = sha1($recipientJson);
                $case->setEntries('hash', $hashToValidateReroute);

                $this->casedataRepository->save($case);
            }
        } catch (\Exception $e) {
            $caseAction = false;
            $caseResponse = null;
            $this->logger->error($e->getMessage(), ['quote' => $quote ?? null]);

            if (isset($quote)) {
                try {
                    /** @var \Signifyd\Connect\Model\Casedata $existingCase */
                    $existingCase = $this->casedataFactory->create();
                    $this->casedataResourceModel->load($existingCase, $quote->getId(), 'quote_id');

                    if ($existingCase->isEmpty() === false &&
                        $existingCase->getPolicyName() === Casedata::PRE_AUTH
                    ) {
                        $this->casedataResourceModel->delete($existingCase);
                        $this->logger->info(
                            "Pre auth case deleted for quote {$quote->getId()} to allow post-auth fallback",
                            ['quote' => $quote]
                        );
                    }
                } catch (\Exception $deleteException) {
                    $this->logger->error(
                        'Failed to remove pre auth case: ' . $deleteException->getMessage(),
                        ['quote' => $quote]
                    );
                }
            }
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
}
