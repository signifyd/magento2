<?php

namespace Signifyd\Connect\Controller\Adminhtml\Webhooks;

use Magento\Backend\App\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem\DirectoryList;
use Signifyd\Connect\Model\WebhookLink;
use Signifyd\Core\Api\WebhooksApiFactory;
use Signifyd\Models\WebhookFactory;
use Signifyd\Connect\Helper\ConfigHelper;

class Register extends Action
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

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
     * @var DirectoryList
     */
    protected $directory;

    /**
     * Register constructor.
     * @param Action\Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param WebhookLink $webhookLink
     * @param WebhooksApiFactory $webhooksApiFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param WebhookFactory $webhookFactory
     * @param ConfigHelper $configHelper
     * @param DirectoryList $directory
     */
    public function __construct(
        Action\Context $context,
        ScopeConfigInterface $scopeConfig,
        WebhookLink $webhookLink,
        WebhooksApiFactory $webhooksApiFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        WebhookFactory $webhookFactory,
        ConfigHelper $configHelper,
        DirectoryList $directory
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->webhookLink = $webhookLink;
        $this->webhooksApiFactory = $webhooksApiFactory;
        $this->storeManager = $storeManager;
        $this->webhookFactory = $webhookFactory;
        $this->configHelper = $configHelper;
        $this->directory = $directory;
    }

    public function execute()
    {
        try {
            $websiteId = $this->_request->getParam('website');
            $storeId = $this->_request->getParam('store');

            if (empty($websiteId) == false) {
                $scopeType = 'stores';
                $scopeCode = $this->storeManager->getWebsite($websiteId)->getDefaultStore()->getId();
            } elseif (empty($storeId) == false) {
                $scopeType = 'stores';
                $scopeCode = $storeId;
            } else {
                $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
                $scopeCode = null;
            }

            $apiKey = $this->scopeConfig->getValue('signifyd/general/key', $scopeType, $scopeCode);
            $url = $this->webhookLink->getUrl();
            $args = [
                'apiKey' => $apiKey,
                'logLocation' => $this->directory->getPath('log')
            ];

            $webhooksApiGet = $this->webhooksApiFactory->create(['args' => $args]);

            /**
             * @var \Signifyd\Core\Response\WebhooksBulkResponse $bulkResponse
             */
            $bulkResponseGet = $webhooksApiGet->getWebhooks();

            $decisionMadeIsSet = false;
            $caseReviewIsSet = false;

            foreach ($bulkResponseGet->getObjects() as $webhook) {
                $webhooksApiCancel = $this->webhooksApiFactory->create(['args' => $args]);

                if ($webhook->eventType === 'DECISION_MADE') {
                    $decisionMadeIsSet = true;
                }

                if ($webhook->eventType === 'CASE_REVIEW') {
                    $caseReviewIsSet = true;
                }

                if ($webhook->eventType === 'GUARANTEE_COMPLETION') {
                    $webhooksApiCancel->deleteWebhook($webhook);
                }
            }

            if ($decisionMadeIsSet === false || $caseReviewIsSet === false) {
                $webhooksApiCreate = $this->webhooksApiFactory->create(['args' => $args]);
                $webhooksToCreate = [];

                if ($decisionMadeIsSet === false) {
                    $webHookDecisionMade = $this->webhookFactory->create();
                    $webHookDecisionMade->setEvent('DECISION_MADE');
                    $webHookDecisionMade->setUrl($url);
                    $webhooksToCreate[] = $webHookDecisionMade;
                }

                if ($caseReviewIsSet === false) {
                    $webHookDecisionMade = $this->webhookFactory->create();
                    $webHookDecisionMade->setEvent('CASE_REVIEW');
                    $webHookDecisionMade->setUrl($url);
                    $webhooksToCreate[] = $webHookDecisionMade;
                }

                $webhooksApiCreate->createWebhooks($webhooksToCreate);
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
