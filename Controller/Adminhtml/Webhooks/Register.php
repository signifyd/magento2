<?php

namespace Signifyd\Connect\Controller\Adminhtml\Webhooks;

use Magento\Backend\App\Action;
use Magento\Framework\Exception\LocalizedException;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\WebhookLink;
use Signifyd\Core\Api\WebhooksApiFactory;
use Signifyd\Models\WebhookFactory;
use Signifyd\Models\WebhookV2Factory;

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
     * @var \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public $storeManager;

    /**
     * @var WebhookFactory
     */
    public $webhookFactory;

    /**
     * @var WebhookV2Factory
     */
    public $webhookV2Factory;

    /**
     * @var Client
     */
    public $client;

    /**
     * Register constructor.
     *
     * @param Action\Context $context
     * @param WebhookLink $webhookLink
     * @param WebhooksApiFactory $webhooksApiFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param WebhookFactory $webhookFactory
     * @param WebhookV2Factory $webhookV2Factory
     * @param Client $client
     */
    public function __construct(
        Action\Context $context,
        WebhookLink $webhookLink,
        WebhooksApiFactory $webhooksApiFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        WebhookFactory $webhookFactory,
        WebhookV2Factory $webhookV2Factory,
        Client $client
    ) {
        parent::__construct($context);
        $this->webhookLink = $webhookLink;
        $this->webhooksApiFactory = $webhooksApiFactory;
        $this->storeManager = $storeManager;
        $this->webhookFactory = $webhookFactory;
        $this->webhookV2Factory = $webhookV2Factory;
        $this->client = $client;
    }

    /**
     * Execute method.
     *
     * @return \Magento\Backend\Model\View\Result\Redirect|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $url = $this->webhookLink->getUrl();
            $isWebhookRegistered = false;
            $webhooksV2ToDelete = [];
            $webhooksV2Api = $this->client->getSignifydWebhookV2Api();
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
                $webhooksV2ApiCreate = $this->client->getSignifydWebhookV2Api();
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

            $webhooksApi = $this->client->getSignifydWebhookApi();
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
                        $webhooksV2Api = $this->client->getSignifydWebhookV2Api();
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
            return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        }

        $this->messageManager->addSuccessMessage(__('The webhook was registred successfully.'));
        return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
    }
}
