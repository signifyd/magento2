<?php

namespace Signifyd\Connect\Controller\Adminhtml\Webhooks;

use Magento\Backend\App\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\WebhookLink;
use Signifyd\Core\Api\WebhooksApiFactory;
use Signifyd\Models\WebhookFactory;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\App\RequestInterface;
class Register extends Action
{
    /**
     * @var WebhookLink
     */
    public $webhookLink;

    /**
     * @var WebhooksApiFactory
     */
    public $webhooksApiFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var WebhookFactory
     */
    public $webhookFactory;

    /**
     * @var Client
     */
    public $client;

    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfigInterface;

    /**
     * @var RequestInterface
     */
    public $request;

    /**
     * Register constructor.
     *
     * @param Action\Context $context
     * @param WebhookLink $webhookLink
     * @param WebhooksApiFactory $webhooksApiFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param WebhookFactory $webhookFactory
     * @param Client $client
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param RequestInterface $request
     */
    public function __construct(
        Action\Context $context,
        WebhookLink $webhookLink,
        WebhooksApiFactory $webhooksApiFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        WebhookFactory $webhookFactory,
        Client $client,
        ScopeConfigInterface $scopeConfigInterface,
        RequestInterface $request
    ) {
        parent::__construct($context);
        $this->webhookLink = $webhookLink;
        $this->webhooksApiFactory = $webhooksApiFactory;
        $this->storeManager = $storeManager;
        $this->webhookFactory = $webhookFactory;
        $this->client = $client;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->request = $request;
    }

    /**
     * Execute method.
     *
     * @return Redirect|ResponseInterface|ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $storeId = (int) $this->getRequest()->getParam('store');
        $websiteId = (int) $this->getRequest()->getParam('website');

        if ($storeId) {
            $store = $this->storeManager->getStore($storeId);
            $scopeType = 'store';
            $scopeCode = $store->getCode();
        } elseif ($websiteId) {
            $website = $this->storeManager->getWebsite($websiteId);
            $scopeType = 'website';
            $scopeCode = $website->getCode();
        } else {
            $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
            $scopeCode = null;
        }

        try {
            $url = $this->webhookLink->getUrl();
            $teamId = $this->scopeConfigInterface->getValue(
                'signifyd/webhook/team_id',
                $scopeType,
                $scopeCode
            );

            if (isset($teamId) === false) {
                throw new LocalizedException(__("Team ID was not provided."));
            }

            $webhooksApi = $this->client->getSignifydWebhookApi();
            /** @var \Signifyd\Core\Response\WebhooksResponse $webHookV3Creation */
            $webHookV3Creation = $webhooksApi->createWebhooks($teamId, ['url' => $url]);
            $webHookV3Id = $webHookV3Creation->getId();

            if (isset($webHookV3Id) === false) {
                throw new LocalizedException(__("Failed to create webhook"));
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('There was a problem registering the webooks: ' . $e->getMessage())
            );
            return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        }

        $this->messageManager->addSuccessMessage(__('The webhook was registred successfully.'));
        return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
    }
}
