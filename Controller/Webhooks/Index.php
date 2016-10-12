<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Controller\Webhooks;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Helper\SignifydAPIMagento;
use Signifyd\Connect\Helper\LogHelper;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\CaseRetry;

/**
 * Controller action for handling webhook posts from Signifyd service
 */
class Index extends Action
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_coreConfig;

    /**
     * @var \Signifyd\Connect\Helper\LogHelper
     */
    protected $_logger;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var SignifydAPIMagento
     */
    protected $_api;

    /**
     * @var DateTime
     */
    protected $_dateTime;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param DateTime $dateTime
     * @param LogHelper $logger
     * @param SignifydAPIMagento $api
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        DateTime $dateTime,
        LogHelper $logger,
        SignifydAPIMagento $api
    ) {
        parent::__construct($context);
        $this->_coreConfig = $scopeConfig;
        $this->_logger = $logger;
        $this->_objectManager = $context->getObjectManager();
        $this->_api = $api;
    }

    // NOTE: Magento may deprecate responses in the future in favor of results.
    protected function Result200()
    {
        $this->getResponse()->setStatusCode(Http::STATUS_CODE_200);
    }

    protected function Result400()
    {
        $this->getResponse()->setStatusCode(Http::STATUS_CODE_400);
    }

    protected function Result403()
    {
        $this->getResponse()->setStatusCode(Http::STATUS_CODE_403);
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

    /**
     * @param mixed $request
     * @return array|null
     */
    protected function initRequest($request)
    {
        /** @var $order \Magento\Sales\Model\Order */
        $order = $this->_objectManager->get('Magento\Sales\Model\Order')->loadByIncrementId($request->orderId);
        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $this->_objectManager->get('Signifyd\Connect\Model\Casedata');
        $case->load($request->orderId);

        return array(
            "case" => $case,
            "order" => $order,
            "request" => $request
        );
    }

    public function execute()
    {
        if (!$this->_api->enabled()) {
            $this->getResponse()->appendBody("This plugin is not currently enabled");
            $this->Result400();
            return;
        }

        $rawRequest = $this->getRawPost();

        $request = $this->getRequest();
        $hash = $request->getHeader('X-SIGNIFYD-SEC-HMAC-SHA256');
        $topic = $request->getHeader('X-SIGNIFYD-TOPIC');
        if ($hash == null) {
            $this->getResponse()->appendBody("You have successfully reached the webhook endpoint");
            $this->Result200();
            return;
        }

        if ($this->_api->validWebhookRequest($rawRequest, $hash, $topic)) {
            // For the webhook test, all of the request data will be invalid
            if ($topic === 'cases/test') {
                $this->Result200();
                return;
            }

            $request = json_decode($rawRequest);
            $caseData = $this->initRequest($request);
            $caseObj = $this->_objectManager->create('Signifyd\Connect\Model\Casedata');
            $caseObj->updateCase($caseData);
            $this->Result200();
            return;
        } else {
            $this->Result403();
            return;
        }
    }

}
