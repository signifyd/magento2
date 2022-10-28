<?php

namespace Signifyd\Connect\Controller\Adminhtml\Webhooks;

use Magento\Backend\App\Action;
use Magento\Framework\Exception\LocalizedException;
use Signifyd\Connect\Model\WebhookLink;
use Signifyd\Core\Api\WebhooksApiFactory;
use Signifyd\Models\WebhookFactory;
use Signifyd\Models\WebhookV2Factory;
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
     * @var WebhookV2Factory
     */
    protected $webhookV2Factory;

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
     * @param WebhookV2Factory $webhookV2Factory
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        Action\Context $context,
        WebhookLink $webhookLink,
        WebhooksApiFactory $webhooksApiFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        WebhookFactory $webhookFactory,
        WebhookV2Factory $webhookV2Factory,
        ConfigHelper $configHelper
    ) {
        parent::__construct($context);
        $this->webhookLink = $webhookLink;
        $this->webhooksApiFactory = $webhooksApiFactory;
        $this->storeManager = $storeManager;
        $this->webhookFactory = $webhookFactory;
        $this->webhookV2Factory = $webhookV2Factory;
        $this->configHelper = $configHelper;
    }

    public function execute()
    {
        try {
            $url = $this->webhookLink->getUrl();
            $isWebhookRegistered = false;
            $webhooksV2ToDelete = [];
            $webhooksV2Api = $this->configHelper->getSignifydWebhookV2Api();
            $webhooksV2 = $webhooksV2Api->getWebhooks();

            if (isset($webhooksV2->getObjects()[0])) {
                $team = $webhooksV2->getObjects()[0]->getTeam();
                $teamId = isset($team) && is_array($team) ? $team['teamId'] : null;

                /** @var \Signifyd\Core\Response\WebhooksV2Response $webHookV2 */
                foreach ($webhooksV2->getObjects() as $webHookV2) {
                    if ($webHookV2->getUrl() === $url) {
                        $webhooksV2ToDelete[] = $webHookV2->getId();
                    }
                }
            } else {
                $webhooksV2ApiCreate = $this->configHelper->getSignifydWebhookV2Api();
                $webHookGuaranteeCompletion = $this->webhookV2Factory->create();
                $webHookGuaranteeCompletion->setEvent('CASE_CREATION');
                $webHookGuaranteeCompletion->setUrl($url);
                $webhooksToCreate = [$webHookGuaranteeCompletion];
                $createResponse = $webhooksV2ApiCreate->createWebhooks($webhooksToCreate);

                if (isset($createResponse->getObjects()[0])) {
                    $teamId = $createResponse->getObjects()[0]->getTeam()['teamId'];
                    $webhooksV2ToDelete[] = $createResponse->getObjects()[0]->getId();
                } else {
                    $teamId = null;
                }
            }

            if (isset($teamId) === false) {
                throw new LocalizedException(__("Failed to get team id"));
            }

            $webhooksApi = $this->configHelper->getSignifydWebhookApi();
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
                /** @var \Signifyd\Core\Response\WebhooksResponse $webHookV3Creation */
                $webHookV3Creation = $webhooksApi->createWebhooks($teamId, ['url' => $url]);

                $webHookV3Id = $webHookV3Creation->getId();
                $isWebhookRegistered = isset($webHookV3Id);
            }

            if ($isWebhookRegistered) {
                if (empty($webhooksV2ToDelete) === false) {
                    foreach ($webhooksV2ToDelete as $webhookV2ToDelete) {
                        $webhooksV2Api = $this->configHelper->getSignifydWebhookV2Api();
                        $webhooksV2Api->deleteWebhook($webhookV2ToDelete);
                    }
                }
            } else {
                throw new LocalizedException(__("Failed to create webhook"));
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('There was a problem registering the webooks: ' . $e->getMessage())
            );

            return $this->_redirect($this->_redirect->getRefererUrl());
        }

        $this->messageManager->addSuccessMessage(__('The webhook was registred successfully.'));

        return $this->_redirect($this->_redirect->getRefererUrl());
    }
}
