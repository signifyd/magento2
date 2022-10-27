<?php

namespace Signifyd\Connect\Controller\Adminhtml\Webhooks;

use Magento\Backend\App\Action;
use Signifyd\Connect\Model\WebhookLink;
use Signifyd\Core\Api\WebhooksApiFactory;
use Signifyd\Models\WebhookFactory;
use Signifyd\Connect\Helper\ConfigHelper;

class Register extends Action
{
    /**
     * @var WebhookLink
     */
    protected $webhookLink;

    /**
     * @var WebhooksApiFactory
     */
    protected $webhooksApiFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    protected $storeManager;

    /**
     * @var WebhookFactory
     */
    protected $webhookFactory;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * Register constructor.
     * @param Action\Context $context
     * @param WebhookLink $webhookLink
     * @param WebhooksApiFactory $webhooksApiFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param WebhookFactory $webhookFactory
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        Action\Context $context,
        WebhookLink $webhookLink,
        WebhooksApiFactory $webhooksApiFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        WebhookFactory $webhookFactory,
        ConfigHelper $configHelper
    ) {
        parent::__construct($context);
        $this->webhookLink = $webhookLink;
        $this->webhooksApiFactory = $webhooksApiFactory;
        $this->storeManager = $storeManager;
        $this->webhookFactory = $webhookFactory;
        $this->configHelper = $configHelper;
    }

    public function execute()
    {
        try {
            $url = $this->webhookLink->getUrl();
            $webhooksApi = $this->configHelper->getSignifydWebhookApi();
            $isWebhookRegistered = false;
            //TODO: register webhook disabled until there is a way to get the teamId from the api
            $teamId = 2019016;

            /**
             * @var \Signifyd\Core\Response\WebhooksResponse $bulkResponse
             */
            $webhookResponse = $webhooksApi->getWebhooks($teamId);

            foreach ($webhookResponse->getObjects() as $webhook) {
                if ($webhook->getUrl() === $url) {
                    if ($isWebhookRegistered) {
                        $webhooksApi->deleteWebhook($teamId, $webhook->getId());
                    } else {
                        $isWebhookRegistered = true;
                    }
                }
            }

            if ($isWebhookRegistered === false) {
                $webhooksApi->createWebhooks($teamId, ['url' => $url]);
            }
        } catch (\Exception $e) {
            $exceptionMessage = method_exists($e, 'getMessege') ? $e->getMessege() : '';

            $this->messageManager->addErrorMessage(
                __('There was a problem registering the webooks: ' . $exceptionMessage)
            );
        }

        $this->messageManager->addSuccessMessage(__('The webhook was registred successfully.'));

        return $this->_redirect($this->_redirect->getRefererUrl());
    }
}
