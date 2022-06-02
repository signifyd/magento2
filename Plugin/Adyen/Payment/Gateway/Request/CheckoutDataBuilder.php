<?php

namespace Signifyd\Connect\Plugin\Adyen\Payment\Gateway\Request;

use Adyen\Payment\Gateway\Request\CheckoutDataBuilder as AdyenCheckoutDataBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\Quote\Model\QuoteFactory;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Helper\DeviceHelper;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\WebhookLink;
use Signifyd\Core\Api\WebhooksApiFactory;
use Signifyd\Models\WebhookFactory;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Model\TRAPreAuth\ScaEvaluation;

class CheckoutDataBuilder
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
     * @var DeviceHelper
     */
    protected $deviceHelper;

    /**
     * @var int
     */
    protected $quoteId;

    /**
     * @var RequestHttp
     */
    protected $requestHttp;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepositoryInterface;

    /**
     * @var PurchaseHelper
     */
    protected $purchaseHelper;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var WebhooksApiFactory
     */
    protected $webhooksApiFactory;

    /**
     * @var WebhookFactory
     */
    protected $webhookFactory;

    /**
     * @var WebhookLink
     */
    protected $webhookLink;

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
     * CheckoutDataBuilder constructor.
     * @param QuoteResourceModel $quoteResourceModel
     * @param QuoteFactory $quoteFactory
     * @param DeviceHelper $deviceHelper
     * @param RequestHttp $requestHttp
     * @param JsonSerializer $jsonSerializer
     * @param ProductRepositoryInterface $productRepositoryInterface
     * @param PurchaseHelper $purchaseHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param WebhooksApiFactory $webhooksApiFactory
     * @param WebhookFactory $webhookFactory
     * @oaram WebhookLink $webhookLink
     * @param ConfigHelper $configHelper
     * @param Logger $logger
     * @param StoreManagerInterface $storeManagerInterface
     * @param ScaEvaluation $scaEvaluation
     */
    public function __construct(
        QuoteResourceModel $quoteResourceModel,
        QuoteFactory $quoteFactory,
        DeviceHelper $deviceHelper,
        RequestHttp $requestHttp,
        JsonSerializer $jsonSerializer,
        ProductRepositoryInterface $productRepositoryInterface,
        PurchaseHelper $purchaseHelper,
        ScopeConfigInterface $scopeConfig,
        WebhooksApiFactory $webhooksApiFactory,
        WebhookFactory $webhookFactory,
        WebhookLink $webhookLink,
        ConfigHelper $configHelper,
        Logger $logger,
        StoreManagerInterface $storeManagerInterface,
        ScaEvaluation $scaEvaluation
    ) {
        $this->quoteResourceModel = $quoteResourceModel;
        $this->quoteFactory = $quoteFactory;
        $this->deviceHelper = $deviceHelper;
        $this->requestHttp = $requestHttp;
        $this->jsonSerializer = $jsonSerializer;
        $this->productRepositoryInterface = $productRepositoryInterface;
        $this->purchaseHelper = $purchaseHelper;
        $this->scopeConfig = $scopeConfig;
        $this->webhooksApiFactory = $webhooksApiFactory;
        $this->webhookFactory = $webhookFactory;
        $this->webhookLink = $webhookLink;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->scaEvaluation = $scaEvaluation;
    }

    public function beforeBuild(AdyenCheckoutDataBuilder $subject, array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        /** @var \Magento\Sales\Model\Order $order */
        $this->quoteId = $payment->getOrder()->getQuoteId();
    }

    public function afterBuild(AdyenCheckoutDataBuilder $subject, $request)
    {
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->quoteFactory->create();
        $this->quoteResourceModel->load($quote, $this->quoteId);

        if ($this->configHelper->isEnabled($quote) == false) {
            return $request;
        }

        $storeId = $this->storeManagerInterface->getStore()->getId();

        $adyenProxyEnabled = $this->scopeConfig->isSetFlag(
            'signifyd/proxy/adyen_enable',
            'stores',
            $storeId
        );

        $scaEvaluation = $this->scaEvaluation->getScaEvaluation($quote->getId());

        if ($scaEvaluation !== false) {
            $executeThreeD = null;
            $scaExemption = null;

            switch ($scaEvaluation->outcome) {
                case 'REQUEST_EXEMPTION':
                    $placement = $scaEvaluation->exemptionDetails->placement;

                    if ($placement === 'AUTHENTICATION') {
                        $executeThreeD = 'True';
                        $scaExemption = 'tra';
                    } elseif ($placement === 'AUTHORIZATION') {
                        $executeThreeD = 'False';
                        $scaExemption = 'tra';
                    }

                    break;

                case 'REQUEST_EXCLUSION':
                case 'DELEGATE_TO_PSP':
                    $executeThreeD = '';
                    $scaExemption = '';
                    break;
            }

            $request['body']['additionalData']['executeThreeD'] = $executeThreeD;
            $request['body']['additionalData']['scaExemption'] = $scaExemption;
        }

        if ($adyenProxyEnabled === false) {
            return $request;
        }

        $taxAmount = $quote->getShippingAddress()->isEmpty() ?
            $quote->getBillingAddress()->getTaxAmount() :
            $quote->getShippingAddress()->getTaxAmount();
        $discountAmount = $quote->getShippingAddress()->isEmpty() ?
            $quote->getBillingAddress()->getDiscountAmount() :
            $quote->getShippingAddress()->getDiscountAmount();
        $magentoRequestJson = $this->requestHttp->getContent();
        $magentoRequest = $this->jsonSerializer->unserialize($magentoRequestJson);

        $teamId = $this->getTeamId($quote);
        $customerId = $quote->getCustomerId();

        if (isset($magentoRequest['paymentMethod']) &&
            isset($magentoRequest['paymentMethod']['additional_data']) &&
            isset($magentoRequest['paymentMethod']['additional_data']['cardBin'])
        ) {
            $request['body']['additionalData']['bin'] = $magentoRequest['paymentMethod']['additional_data']['cardBin'];
        }

        if (isset($customerId)) {
            $customerEmail = $quote->getCustomerEmail();
        } else {
            $customerEmail = $quote->getBillingAddress()->getEmail();
        }

        $request['body']['additionalData']['teamId'] = $teamId;
        $request['body']['additionalData']['checkoutAttemptId'] = uniqid();
        $request['body']['additionalData']['enhancedSchemeData.dutyAmount'] = $this->processAmount($taxAmount);
        $request['body']['additionalData']['riskdata.basket.item0.receiverEmail'] = $customerEmail;

        if ($discountAmount) {
            $request['body']['additionalData']['riskdata.promotions.promotion0.promotionDiscountAmount'] =
                $discountAmount;
        }

        if ($quote->getCouponCode()) {
            $request['body']['additionalData']['riskdata.promotions.promotion0.promotionCode'] =
                $quote->getCouponCode();
        }

        if ($this->deviceHelper->isDeviceFingerprintEnabled()) {
            $request['body']['additionalData']['orderSessionId'] =
                $this->deviceHelper->generateFingerprint($quote->getId());
        }

        if (isset($request['body']['lineItems']) === false || empty($request['body']['lineItems']) === true) {
            /** @var \Magento\Quote\Model\Quote\Item $item */
            foreach ($quote->getAllItems() as $i => $item) {
                $children = $item->getChildrenItems();

                if (is_array($children) == false || empty($children)) {
                    $itemPrice = floatval(number_format($item->getPriceInclTax(), 2, '.', ''));

                    if ($itemPrice <= 0 && $item->getParentItem()) {
                        if ($item->getParentItem()->getProductType() === 'configurable') {
                            $itemPrice = floatval(number_format(
                                $item->getParentItem()->getPriceInclTax(),
                                2,
                                '.',
                                ''
                            ));
                        }
                    }

                    $product = $this->productRepositoryInterface->getById($item->getProduct()->getId());
                    $productImageUrl = $this->purchaseHelper->getProductImage($product);

                    $request['body']['lineItems'][$i]['amountIncludingTax'] = $this->processAmount($itemPrice);
                    $request['body']['lineItems'][$i]['description'] = $item->getName();
                    $request['body']['lineItems'][$i]['quantity'] = (int)$item->getQty();
                    $request['body']['lineItems'][$i]['productUrl'] = $product->getProductUrl();
                    $request['body']['lineItems'][$i]['imageUrl'] = $productImageUrl;
                }
            }
        }

        $address = $quote->getShippingAddress()->getCity() !== null ?
            $quote->getShippingAddress() : $quote->getBillingAddress();
        $shippingData = $this->getAddressData($quote->getShippingAddress());
        $billingData = $this->getAddressData($quote->getBillingAddress());
        $signifydAddress = $this->purchaseHelper->formatSignifydAddress($address);
        $adyenAddress = $this->getAdyenAddress($signifydAddress);

        if ($quote->isVirtual()) {
            $deliveryAddressIndicator = 'digitalGoods';
        } elseif ($shippingData == $billingData) {
            $deliveryAddressIndicator = 'shipToBillingAddress';
        } elseif ($quote->getShippingAddress()->getCustomerAddressId() == false) {
            $deliveryAddressIndicator = 'shipToNewAddress';
        } else {
            $deliveryAddressIndicator = 'other';
        }

        if (!isset($request['body']['merchantRiskIndicator']['deliveryTimeframe'])) {
            if ($quote->isVirtual()) {
                $deliveryTimeframe = 'electronicDelivery';
            } else {
                $deliveryTimeframe = 'twoOrMoreDaysShipping';
            }

            $request['body']['merchantRiskIndicator']['deliveryTimeframe'] = $deliveryTimeframe;
        }

        if (isset($customerId)) {
            $createdAt = $quote->getCustomer()->getCreatedAt();
            $transactionCount = $this->purchaseHelper->getPastTransactionsYear($quote->getCustomerId());
            $purchaseCount = $this->purchaseHelper->getPurchasesLast6Months($quote->getCustomerId());

            $createdAt = str_replace(' ', 'T', $createdAt) . "+00:00";
            $request['body']['accountInfo']['accountCreationDate'] = $createdAt;
            $request['body']['accountInfo']['pastTransactionsYear'] = $transactionCount;
            $request['body']['accountInfo']['purchasesLast6Months'] = $purchaseCount;
        }

        $request['body']['merchantRiskIndicator']['deliveryAddressIndicator'] = $deliveryAddressIndicator;
        $request['body']['deliveryAddress'] = $adyenAddress;

        return $request;
    }

    public function getAddressData(\Magento\Quote\Model\Quote\Address $address)
    {
        $data = implode('', $address->getStreet());
        $data .= $address->getPostcode();
        $data .= $address->getCity();
        $data .= $address->getRegion();
        $data .= $address->getCountry();

        return $data;
    }

    public function getAdyenAddress($signifydAddress)
    {
        $adyenAddress = [];

        if (isset($signifydAddress['unit'])) {
            $adyenAddress['houseNumberOrName'] = $signifydAddress['unit'];
        } else {
            $adyenAddress['houseNumberOrName'] = 'NA';
        }

        $adyenAddress['country'] = $signifydAddress['countryCode'];
        $adyenAddress['city'] = $signifydAddress['city'];
        $adyenAddress['street'] = $signifydAddress['streetAddress'];
        $adyenAddress['stateOrProvince'] = $signifydAddress['provinceCode'];
        $adyenAddress['postalCode'] = $signifydAddress['postalCode'];

        return $adyenAddress;
    }

    public function getTeamId(\Magento\Quote\Model\Quote $quote)
    {
        try {
            $storeId = $quote->getStoreId();

            if (isset($storeId)) {
                $scopeType = 'stores';
                $scopeCode = $storeId;
            } else {
                $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
                $scopeCode = null;
            }

            $apiKey = $this->scopeConfig->getValue('signifyd/general/key', $scopeType, $scopeCode);
            $url = $this->webhookLink->getUrl();
            $args = ['apiKey' => $apiKey];

            /** @var \Signifyd\Core\Api\WebhooksApi $webhooksApiGet */
            $webhooksApiGet = $this->webhooksApiFactory->create(['args' => $args]);

            /**
             * @var \Signifyd\Core\Response\WebhooksBulkResponse $bulkResponse
             */
            $bulkResponseGet = $webhooksApiGet->getWebhooks();

            if (isset($bulkResponseGet->getObjects()[0])) {
                $teamId = $bulkResponseGet->getObjects()[0]->getTeam()['teamId'];
            } else {
                $webhooksApiCreate = $this->webhooksApiFactory->create(['args' => $args]);
                $webHookGuaranteeCompletion = $this->webhookFactory->create();
                $webHookGuaranteeCompletion->setEvent('DECISION_MADE');
                $webHookGuaranteeCompletion->setUrl($url);
                $webhooksToCreate = [$webHookGuaranteeCompletion];
                $createResponse = $webhooksApiCreate->createWebhooks($webhooksToCreate);

                if (isset($createResponse->getObjects()[0])) {
                    $teamId = $createResponse->getObjects()[0]->getTeam()['teamId'];
                } else {
                    $teamId = null;
                    $this->logger->info("There was a problem getting teamId");
                }
            }
        } catch (\Exception $e) {
            $teamId = null;
            $this->logger->info($e->getMessage());
        }

        return $teamId;
    }

    public function processAmount($amount)
    {
        return (int) number_format($amount * 100, 0, '', '');
    }
}
