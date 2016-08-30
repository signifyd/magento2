<?php
/**
 * Copyright © 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Controller\Webhooks;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Helper\SignifydAPIMagento;
use Signifyd\Connect\Helper\LogHelper;

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

    /**
     * @param $caseData
     */
    protected function updateCase($caseData)
    {
        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $caseData['case'];
        $request = $caseData['request'];
        $order = $caseData['order'];

        // TODO: Since these actions are fairly overlapped at this point,
        // might be a good idea to unify them.
        $orderAction = array("action" => null, "reason" => '');
        if (isset($request->score) && $case->getScore() != $request->score) {
            $case->setScore($request->score);
            $order->setSignifydScore($request->score);
            $orderAction = $this->handleScoreChange($caseData) ?: $orderAction;
        }

        if (isset($request->status) && $case->getSignifydStatus() != $request->status) {
            $case->setSignifydStatus($request->status);
            $orderAction = $this->handleStatusChange($caseData) ?: $orderAction;
        }

        if (isset($request->guaranteeDisposition) && $case->getGuarantee() != $request->guaranteeDisposition) {
            $case->setGuarantee($request->guaranteeDisposition);
            $order->setSignifydGuarantee($request->guaranteeDisposition);
            $orderAction = $this->handleGuaranteeChange($caseData) ?: $orderAction;
        }
        $case->setCode($request->caseId);
        $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
        $case->save();

        $order->setSignifydCode($request->caseId);
        $order->save();
        $this->updateOrder($caseData, $orderAction);
    }

    /**
     * @param array $caseData
     * @param string $orderAction
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function updateOrder($caseData, $orderAction)
    {
        /** @var $order \Magento\Sales\Model\Order */
        $order = $caseData['order'];
        switch ($orderAction["action"]) {
            case "hold":
                if ($order->canHold()) {
                    $order->hold()->save();
                }
                break;
            case "unhold":
                if ($order->canUnhold()) {
                    $order->unhold()->save();
                }
                break;
            case "cancel":
                // Can't cancel if order is on hold
                if ($order->canUnhold()) {
                    $order = $order->unhold();
                }
                if ($order->canCancel()) {
                    $order->cancel()->save();
                }
                break;
        }
        $order->addStatusHistoryComment("Signifyd set status to {$orderAction["action"]} because {$orderAction["reason"]}");
        $order->save();
    }

    /**
     * @param $caseData
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return string
     */
    protected function handleScoreChange($caseData)
    {
        $threshHold = (int)$this->_coreConfig->getValue('signifyd/advanced/hold_orders_threshold');
        $holdBelowThreshold = $this->_coreConfig->getValue('signifyd/advanced/hold_orders');
        if ($holdBelowThreshold && $caseData['request']->score <= $threshHold) {
            return array("action"=>"hold", "reason"=>"score threshold failure");
        }
        return null;
    }

    /**
     * @param $caseData
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return string
     */
    protected function handleStatusChange($caseData)
    {
        $holdBelowThreshold = $this->_coreConfig->getValue('signifyd/advanced/hold_orders');
        if ($holdBelowThreshold && $caseData['request']->reviewDisposition == 'FRAUDULENT') {
            return array("action"=>"hold", "reason"=>"review returned FRAUDULENT");
        } else {
            if ($holdBelowThreshold && $caseData['request']->reviewDisposition == 'GOOD') {
                return array("action"=>"unhold", "reason"=>"review returned GOOD");
            }
        }
        return null;
    }

    /**
     * @param $caseData
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return string
     */
    protected function handleGuaranteeChange($caseData)
    {
        $negativeAction = $this->_coreConfig->getValue('signifyd/advanced/guarantee_negative_action');
        $positiveAction = $this->_coreConfig->getValue('signifyd/advanced/guarantee_positive_action');

        $request = $caseData['request'];
        if ($request->guaranteeDisposition == 'DECLINED' && $negativeAction != 'nothing') {
            return array("action" => $negativeAction, "reason"=>"guarantee declined");
        } else {
            if ($request->guaranteeDisposition == 'APPROVED' && $positiveAction != 'nothing') {
                return array("action" => $positiveAction, "reason"=>"guarantee approved");
            }
        }
        return null;
    }

    public function execute()
    {
        if(!$this->_api->enabled())
        {
            echo "This plugin is not currently enabled";
            return;
        }

        $rawRequest = $this->getRawPost();

        $request = $this->getRequest();
        $hash = $request->getHeader('X-SIGNIFYD-SEC-HMAC-SHA256');
        $topic = $request->getHeader('X-SIGNIFYD-TOPIC');
        if($hash == null)
        {
            echo "You have successfully reached the webhook endpoint";
            return;
        }

        if ($this->_api->validWebhookRequest($rawRequest, $hash, $topic)) {
            // For the webhook test, all of the request data will be invalid
            if ($topic === 'cases/test') {
                return;
            }

            $request = json_decode($rawRequest);
            $caseData = $this->initRequest($request);
            $this->updateCase($caseData);
        }
    }

}
