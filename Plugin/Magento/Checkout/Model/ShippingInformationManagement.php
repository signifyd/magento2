<?php

namespace Signifyd\Connect\Plugin\Magento\Checkout\Model;

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

class ShippingInformationManagement
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
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * ShippingInformationManagement constructor.
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
     * @param ConfigHelper $configHelper
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
        ConfigHelper $configHelper
    ){
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
        $this->configHelper = $configHelper;
    }

    public function afterSaveAddressInformation($subject, $result, $cartId, $addressInformation)
    {
        if ($this->configHelper->isEnabled()) {
            $this->logger->info("policy validation");

            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $this->quoteRepository->getActive($cartId);
            $policyName = $this->purchaseHelper->getPolicyName($quote->getStoreId());
            $policyRejectMessage = $this->scopeConfigInterface->getValue(
                'signifyd/advanced/policy_pre_auth_reject_message', ScopeInterface::SCOPE_STORES, $quote->getStoreId()
            );

            if ($policyName == 'PRE_AUTH') {
                $this->logger->info("Creating case for quote {$quote->getId()}");
                $case = $this->purchaseHelper->processQuoteData($quote);
                $checkoutToken = $case['purchase']['checkoutToken'];
                $caseResponse = $this->purchaseHelper->postCaseFromQuoteToSignifyd($case, $quote);

                if (isset($caseResponse->recommendedAction) && $caseResponse->recommendedAction == 'ACCEPT') {
                    /** @var $case \Signifyd\Connect\Model\Casedata */
                    $case = $this->casedataFactory->create();
                    $case->setSignifydStatus($caseResponse->status);
                    $case->setCode($caseResponse->caseId);
                    $case->setScore(floor($caseResponse->score));
                    $case->setGuarantee($caseResponse->recommendedAction);
                    $case->setEntriesText("");
                    $case->setCreated(strftime('%Y-%m-%d %H:%M:%S', time()));
                    $case->setUpdated();
                    $case->setMagentoStatus(Casedata::PRE_AUTH);
                    $case->setQuoteId($quote->getId());
                    $case->setCheckoutToken($checkoutToken);
                    $case->setPolicyName(Casedata::PRE_AUTH);

                    $this->casedataResourceModel->save($case);

                    return $result;
                }

                throw new LocalizedException(__($policyRejectMessage));
            }
        }

        return $result;
    }
}
