<?php
namespace Signifyd\Connect\Controller\Index;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Signifyd\Connect\Lib\SDK\Core\SignifydAPI;
use Signifyd\Connect\Lib\SDK\Core\SignifydSettings;
use Signifyd\Connect\Helper\LogHelper;

/**
 * Controller action for handling webhook posts from Signifyd service
 */
class Index extends \Magento\Framework\App\Action\Action
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
     * @var SignifydAPI
     */
    protected $_api;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param ObjectManagerInterface $objectManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        ObjectManagerInterface $objectManager,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->_coreConfig = $scopeConfig;
        $this->_logger = new LogHelper($logger, $scopeConfig);

        try {
            $settings = new SignifydSettings();
            $settings->apiKey = $scopeConfig->getValue('signifyd/general/key');

            $this->_api = new SignifydAPI($settings);
        } catch (\Exception $e) {
            $this->_logger->error($e);
        }
    }

    /**
     * @return string
     */
    private function getRawPost()
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
     * @param $header
     * @return string
     */
    private function getHeader($header)
    {
        // Some frameworks add an extra HTTP_ before the header, so check for both names
        // Header values stored in the $_SERVER variable have dashes converted to underscores, hence str_replace
        $direct = strtoupper(str_replace('-', '_', $header));
        $extraHttp = 'HTTP_' . $direct;

        // Check the $_SERVER global
        if (isset($_SERVER[$direct])) {
            return $_SERVER[$direct];
        } else if (isset($_SERVER[$extraHttp])) {
            return $_SERVER[$extraHttp];
        }

        $this->_logger->error('Valid Header Not Found: ' . $header);
        return '';
    }

    /**
     * @param mixed $request
     * @return array|null
     */
    private function initRequest($request)
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
    private function updateCase($caseData)
    {
        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $caseData['case'];
        $request = $caseData['request'];

        // TODO: Since these actions are fairly overlapped at this point,
        // might be a good idea to unify them.
        $orderAction = null;
        if(isset($request->score) && $case->getScore() != $request->score)
        {
            $case->setScore($request->score);
            $orderAction = $this->handleScoreChange($caseData) ?: $orderAction;
        }

        if(isset($request->status) && $case->getSignifydStatus() != $request->status)
        {
            $case->setSignifydStatus($request->status);
            $orderAction = $this->handleStatusChange($caseData) ?: $orderAction;
        }

        if(isset($request->guaranteeDisposition) && $case->getGuarantee() != $request->guaranteeDisposition)
        {
            $case->setGuarantee($request->guaranteeDisposition);
            $orderAction = $this->handleGuaranteeChange($caseData) ?: $orderAction;
        }
        $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
        $case->save();
        $this->updateOrder($caseData, $orderAction);
    }

    /**
     * @param array $caseData
     * @param string $orderAction
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function updateOrder($caseData, $orderAction)
    {
        /** @var $order \Magento\Sales\Model\Order */
        $order = $caseData['order'];
        switch ($orderAction) {
            case "hold":
                if ($order->canHold()) $order->hold()->save();
                break;
            case "unhold":
                if ($order->canUnhold()) $order->unhold()->save();
                break;
            case "cancel":
                if ($order->canCancel()) $order->cancel()->save();
                break;
        }
    }

    /**
     * @param $caseData
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return string
     */
    private function handleScoreChange($caseData)
    {
        $threshHold = (int)$this->_coreConfig->getValue('signifyd/advanced/hold_orders_threshold');
        $holdBelowThreshold = $this->_coreConfig->getValue('signifyd/advanced/hold_orders');
        if($holdBelowThreshold && $caseData['request']->score <= $threshHold) {
            return "hold";
        }
        return null;
    }

    /**
     * @param $caseData
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return string
     */
    private function handleStatusChange($caseData)
    {
        $holdBelowThreshold = $this->_coreConfig->getValue('signifyd/advanced/hold_orders');
        if($holdBelowThreshold && $caseData['request']->reviewDisposition == 'FRAUDULENT') {
            return "hold";
        } else if($holdBelowThreshold && $caseData['request']->reviewDisposition == 'GOOD') {
            return "unhold";
        }
        return null;
    }

    /**
     * @param $caseData
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return string
     */
    private function handleGuaranteeChange($caseData)
    {
        $negativeAction = $this->_coreConfig->getValue('signifyd/advanced/guarantee_negative_action');
        $positiveAction = $this->_coreConfig->getValue('signifyd/advanced/guarantee_positive_action');

        $request = $caseData['request'];
        if ($request->guaranteeDisposition == 'DECLINED' && $negativeAction != 'nothing') {
            return $negativeAction;
        } else if ($request->guaranteeDisposition == 'APPROVED' && $positiveAction != 'nothing') {
            return $positiveAction;
        }
        return null;
    }

    public function execute()
    {
        $rawRequest = $this->getRawPost();
        $hash = $this->getHeader('X-SIGNIFYD-SEC-HMAC-SHA256');
        $topic = $this->getHeader('X-SIGNIFYD-TOPIC');

        if ($this->_api->validWebhookRequest($rawRequest, $hash, $topic)) {
            // For the webhook test, all of the request data will be invalid
            if($topic === 'cases/test') return;

            $request = json_decode($rawRequest);
            $caseData = $this->initRequest($request);
            $this->updateCase($caseData);
        }
    }

}
