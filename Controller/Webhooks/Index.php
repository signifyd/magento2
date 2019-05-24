<?php
/**
 * Copyright 2019 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Controller\Webhooks;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Signifyd\Connect\Logger\Logger;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\RequestInterface;

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
     * @param Logger $logger
     * @param \Signifyd\Connect\Helper\ConfigHelper $configHelper
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     */
    public function __construct(
        Context $context,
        DateTime $dateTime,
        Logger $logger,
        \Signifyd\Connect\Helper\ConfigHelper $configHelper,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Data\Form\FormKey $formKey
    ) {
        parent::__construct($context);

        $this->logger = $logger;
        $this->objectManager = $context->getObjectManager();
        $this->configHelper = $configHelper;
        $this->orderFactory = $orderFactory;

        // Compatibility with Magento 2.3+ which required form_key on every request
        // Magento expects class to implement \Magento\Framework\App\CsrfAwareActionInterface but this causes
        // a backward incompatibility to Magento versions below 2.3
        if (interface_exists("\Magento\Framework\App\CsrfAwareActionInterface")) {
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

        return $this->processRequest($request, $hash, $topic);
    }

    public function processRequest($request, $hash, $topic)
    {
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
