<?php
/**
 * Copyright 2019 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Controller\Webhooks;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Signifyd\Connect\Helper\LogHelper;

/**
 * Controller action for handling webhook posts from Signifyd service
 */
class Index extends IndexPure
{
    /**
     * @var \Signifyd\Connect\Helper\LogHelper
     */
    protected $logger;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Signifyd\Connect\Helper\ConfigHelper
     */
    protected $configHelper;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * @param Context $context
     * @param DateTime $dateTime
     * @param LogHelper $logger
     * @param \Signifyd\Connect\Helper\ConfigHelper $configHelper
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     */
    public function __construct(
        Context $context,
        DateTime $dateTime,
        LogHelper $logger,
        \Signifyd\Connect\Helper\ConfigHelper $configHelper,
        \Magento\Sales\Model\OrderFactory $orderFactory
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->objectManager = $context->getObjectManager();
        $this->configHelper = $configHelper;
        $this->orderFactory = $orderFactory;
    }

    /**
     * @return string
     */
    protected function getRawPost()
    {
        if (isset($HTTP_RAW_POST_DATA) && $HTTP_RAW_POST_DATA) {
            return $HTTP_RAW_POST_DATA;
        }

        $post = file_get_contents("php://input");

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

        $this->logger->debug('API: request: ' . $request);
        $this->logger->debug('API: request hash: ' . $hash);
        $this->logger->debug('API: request topic: ' . $topic);

        if ($hash == null || empty($request)) {
            $this->getResponse()->appendBody("You have successfully reached the webhook endpoint");
            $this->getResponse()->setStatusCode(Http::STATUS_CODE_200);
            return;
        }

        $requestJson = json_decode($request);

        if (json_last_error() == JSON_ERROR_NONE) {
            // Test is only verifying that the endpoint is reachable. So we just complete here
            if ($topic === 'cases/test') {
                $this->getResponse()->setStatusCode(Http::STATUS_CODE_200);
                return;
            }

            /** @var $order \Magento\Sales\Model\Order */
            $order = $this->orderFactory->create()->loadByIncrementId($requestJson->orderId);
            /** @var $case \Signifyd\Connect\Model\Casedata */
            $case = $this->objectManager->create('Signifyd\Connect\Model\Casedata')->load($requestJson->orderId);

            $caseData = array(
                "case" => $case,
                "order" => $order,
                "request" => $requestJson
            );

            if ($case->isEmpty()) {
                $message = "Case {$requestJson->orderId} on request not found on Magento";
                $this->getResponse()->appendBody($message);
                $this->logger->debug("API: {$message}");
                $this->getResponse()->setStatusCode(Http::STATUS_CODE_400);
                return;
            }
        } else {
            $message = 'Invalid JSON provided on request body';
            $this->getResponse()->appendBody($message);
            $this->logger->debug("API: {$message}");
            $this->getResponse()->setStatusCode(Http::STATUS_CODE_400);
            return;
        }

        if ($this->configHelper->isEnabled($case) == false) {
            $message = 'This plugin is not currently enabled';
            $this->getResponse()->appendBody($message);
            $this->logger->debug("API: {$message}");
            $this->getResponse()->setStatusCode(Http::STATUS_CODE_400);
            return;
        }

        $this->logger->debug("Api request: " . $request);

        $signifydApi = $this->configHelper->getSignifydApi($case);

        if ($signifydApi->validWebhookRequest($request, $hash, $topic)) {
            /** @var \Signifyd\Connect\Model\Casedata $caseObj */
            $caseObj = $this->objectManager->create('Signifyd\Connect\Model\Casedata');
            $caseObj->updateCase($caseData);
            $this->getResponse()->setStatusCode(Http::STATUS_CODE_200);
            return;
        } else {
            $this->getResponse()->setStatusCode(Http::STATUS_CODE_403);
            return;
        }
    }
}
