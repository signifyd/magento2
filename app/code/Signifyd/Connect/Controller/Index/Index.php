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
     * @param string $topic
     * @return array|null
     */
    private function initRequest($request, $topic)
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

        if(isset($request->score) && $case->getScore() != $request->score)
        {
            $case->setScore($request->score);
            $this->handleScoreChange($caseData);
        }

        if(isset($request->status) && $case->getSignifydStatus() != $request->status)
        {
            $case->setSignifydStatus($request->status);
            $this->handleStatusChange($caseData);
        }

        if(isset($request->guaranteeDisposition) && $case->getGuarantee() != $request->guaranteeDisposition)
        {
            $case->setGuarantee($request->guaranteeDisposition);
            $this->handleGuaranteeChange($caseData);
        }

        $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
        $case->save();
    }

    /**
     * @param $caseData
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function handleScoreChange($caseData)
    {
        /** @var $order \Magento\Sales\Model\Order */
        $order = $caseData['order'];
        $threshHold = (int)$this->_coreConfig->getValue('signifyd/advanced/hold_orders_threshold');
        $holdBelowThreshold = $this->_coreConfig->getValue('signifyd/advanced/hold_orders');
        if($holdBelowThreshold && $caseData['request']->score <= $threshHold) {
            $order->hold();
        }
    }

    /**
     * @param $caseData
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function handleStatusChange($caseData)
    {
        /** @var $order \Magento\Sales\Model\Order */
        $order = $caseData['order'];
        $holdBelowThreshold = $this->_coreConfig->getValue('signifyd/advanced/hold_orders');
        if($holdBelowThreshold && $caseData['request']->reviewDisposition == 'FRAUDULENT') {
            $order->hold();
        } else if($holdBelowThreshold && $caseData['request']->reviewDisposition == 'GOOD') {
            $order->unhold();
        }
    }

    /**
     * @param $caseData
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function handleGuaranteeChange($caseData)
    {
        $negativeAction = $this->_coreConfig->getValue('signifyd/advanced/guarantee_negative_action');
        $positiveAction = $this->_coreConfig->getValue('signifyd/advanced/guarantee_positive_action');

        /** @var $order \Magento\Sales\Model\Order */
        $order = $caseData['order'];
        $request = $caseData['request'];
        if (isset($request->guaranteeDisposition)) {
            if ($request->guaranteeDisposition == 'DECLINED' && $negativeAction != 'nothing') {
                if ($negativeAction == 'hold') {
                    $order->hold();
                } else if ($negativeAction == 'cancel') {
                    $order->cancel();
                }
            } else if ($request->guaranteeDisposition == 'APPROVED' && $positiveAction != 'nothing') {
                if ($positiveAction == 'unhold') {
                    $order->unhold();
                }
            }
        }
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
            $caseData = $this->initRequest($request, $topic);
            $this->updateCase($caseData);
        }
    }
}
