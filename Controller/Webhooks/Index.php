<?php
/**
 * Copyright 2019 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Controller\Webhooks;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Helper\ConfigHelper;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Filesystem\Driver\File;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\App\Emulation;

/**
 * Controller action for handling webhook posts from Signifyd service
 */
class Index extends Action
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var File
     */
    protected $file;

    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var Emulation
     */
    protected $emulation;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * Index constructor.
     * @param Context $context
     * @param DateTime $dateTime
     * @param Logger $logger
     * @param ConfigHelper $configHelper
     * @param FormKey $formKey
     * @param File $file
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param OrderResourceModel $orderResourceModel
     * @param JsonSerializer $jsonSerializer
     * @param ResourceConnection $resourceConnection
     * @param Emulation $emulation
     * @param StoreManagerInterface $storeManagerInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        Context $context,
        DateTime $dateTime,
        Logger $logger,
        ConfigHelper $configHelper,
        FormKey $formKey,
        File $file,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        OrderResourceModel $orderResourceModel,
        JsonSerializer $jsonSerializer,
        ResourceConnection $resourceConnection,
        Emulation $emulation,
        StoreManagerInterface $storeManagerInterface
    ) {
        parent::__construct($context);

        $this->logger = $logger;
        $this->configHelper = $configHelper;
        $this->file = $file;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->orderResourceModel = $orderResourceModel;
        $this->jsonSerializer = $jsonSerializer;
        $this->resourceConnection = $resourceConnection;
        $this->emulation = $emulation;
        $this->storeManagerInterface = $storeManagerInterface;

        // Compatibility with Magento 2.3+ which required form_key on every request
        // Magento expects class to implement \Magento\Framework\App\CsrfAwareActionInterface but this causes
        // a backward incompatibility to Magento versions below 2.3
        if (interface_exists(\Magento\Framework\App\CsrfAwareActionInterface::class)) {
            $request = $this->getRequest();
            if ($request instanceof RequestInterface && $request->isPost() && empty($request->getParam('form_key'))) {
                $request->setParam('form_key', $formKey->getFormKey());
            }
        }
    }

    /**
     * @return string
     */
    protected function getRawPost()
    {
        if (isset($HTTP_RAW_POST_DATA) && $HTTP_RAW_POST_DATA) {
            return $HTTP_RAW_POST_DATA;
        }

        $post = $this->file->fileGetContents("php://input");

        if ($post) {
            return $post;
        }

        return '';
    }

    public function execute()
    {
        $request = $this->getRawPost();
        $hash = $this->getRequest()->getHeader('X-SIGNIFYD-SEC-HMAC-SHA256');
        $topic = $this->getRequest()->getHeader('X-SIGNIFYD-TOPIC');

        $this->logger->debug('WEBHOOK: request: ' . $request);
        $this->logger->debug('WEBHOOK: request hash: ' . $hash);
        $this->logger->debug('WEBHOOK: request topic: ' . $topic);

        return $this->processRequest($request, $hash, $topic);
    }

    public function processRequest($request, $hash, $topic)
    {
        if (empty($hash) || empty($request)) {
            $this->getResponse()->appendBody("You have successfully reached the webhook endpoint");
            $this->getResponse()->setStatusCode(Http::STATUS_CODE_200);
            return;
        }

        try {
            $requestJson = (object) $this->jsonSerializer->unserialize($request);
        } catch (\InvalidArgumentException $e) {
            $message = 'Invalid JSON provided on request body';
            $this->getResponse()->appendBody($message);
            $this->logger->debug("WEBHOOK: {$message}");
            $this->getResponse()->setStatusCode(Http::STATUS_CODE_400);
            return;
        }

        if (isset($requestJson->caseId) === false) {
            $httpCode = Http::STATUS_CODE_200;
            throw new LocalizedException(__("Invalid body, no 'caseId' field found on request"));
        }

        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $this->casedataFactory->create();

        try {
            $this->casedataResourceModel->loadForUpdate($case, (string) $requestJson->caseId, 'code');
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return;
        }

        switch ($topic) {
            case 'cases/test':
                // Test is only verifying that the endpoint is reachable. So we just complete here
                $this->getResponse()->setStatusCode(Http::STATUS_CODE_200);
                return;

            case 'cases/creation':
                if ($this->configHelper->isScoreOnly() === false) {
                    $message = 'Case creation will not be processed by Magento';
                    $this->getResponse()->appendBody($message);
                    $this->logger->debug("WEBHOOK: {$message}");
                    $this->getResponse()->setStatusCode(Http::STATUS_CODE_200);
                    return;
                }
                break;
        }

        $this->emulation->startEnvironmentEmulation(0, 'adminhtml');

        try {
            $httpCode = null;

            if ($case->isEmpty()) {
                $httpCode = Http::STATUS_CODE_400;
                throw new LocalizedException(__("Case {$requestJson->caseId} on request not found on Magento"));
            }

            $signifydWebhookApi = $this->configHelper->getSignifydWebhookApi($case);

            if ($signifydWebhookApi->validWebhookRequest($request, $hash, $topic) == false) {
                $httpCode = Http::STATUS_CODE_403;
                throw new LocalizedException(__("Invalid webhook request"));
            } elseif ($this->configHelper->isEnabled($case) == false) {
                $httpCode = Http::STATUS_CODE_400;
                throw new LocalizedException(__('Signifyd plugin it is not enabled'));
            } elseif ($case->getMagentoStatus() == Casedata::WAITING_SUBMISSION_STATUS) {
                $httpCode = Http::STATUS_CODE_400;
                throw new LocalizedException(__("Case {$requestJson->caseId} it is not ready to be updated"));
            } elseif ($case->getMagentoStatus() == Casedata::PRE_AUTH) {
                $httpCode = Http::STATUS_CODE_200;
                throw new LocalizedException(
                    __("Case {$requestJson->caseId} already completed by synchronous response, no action will be taken")
                );
            }

            $this->logger->info("WEBHOOK: Processing case {$case->getId()}");

            $this->emulation->startEnvironmentEmulation(0, 'adminhtml');
            $this->storeManagerInterface->setCurrentStore($case->getOrder()->getStore()->getStoreId());

            $currentCaseHash = sha1(implode(',', $case->getData()));
            $case->updateCase($requestJson);
            $newCaseHash = sha1(implode(',', $case->getData()));

            if ($currentCaseHash == $newCaseHash) {
                $httpCode = Http::STATUS_CODE_200;
                throw new LocalizedException(
                    __("Case {$requestJson->caseId} already update with this data, no action will be taken")
                );
            }

            $case->updateOrder();

            $this->casedataResourceModel->save($case);
        } catch (\Exception $e) {
            // Triggering case save to unlock case
            if ($case instanceof \Signifyd\Connect\Model\ResourceModel\Casedata) {
                $this->casedataResourceModel->save($case);
            }

            $httpCode = empty($httpCode) ? 403 : $httpCode;
            $this->getResponse()->appendBody($e->getMessage());
            $this->logger->error("WEBHOOK: {$e->getMessage()}");
        }

        $httpCode = empty($httpCode) ? 200 : $httpCode;
        $this->getResponse()->setStatusCode($httpCode);
        $this->emulation->stopEnvironmentEmulation();
    }
}
