<?php
namespace Signifyd\Connect\Controller\Index;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Signifyd\Connect\Lib\SDK\Core\SignifydAPI;
use Signifyd\Connect\Lib\SDK\Core\SignifydSettings;

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
     * @var \Psr\Log\LoggerInterface
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
        $this->_logger = $logger;

        try {
            $settings = new SignifydSettings();
            $settings->apiKey = $scopeConfig->getValue('signifyd/general/key');
            $settings->logInfo = true;

            $this->_api = new SignifydAPI($settings);
            $this->_logger->info(json_encode($settings));
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

        $this->_logger->info('Valid Header Not Found: ' . $header);
        return '';
    }

    /**
     * @param mixed $request
     * @param string $topic
     * @return array|null
     */
    private function initRequest($request, $topic)
    {
        // For the webhook test, all of the request data will be invalid
        if ($topic == "cases/test") return null;

        /** @var $order \Magento\Sales\Model\Order */
        $order = $this->_objectManager->get('Magento\Sales\Model\Order')->loadByIncrementId($request['orderId']);

        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $this->_objectManager->get('Signifyd\Connect\Model\Casedata');
        $case->load($request['orderId']);

        return array(
            "case" => $case,
            "order" => $order,
            "request" => $request
        );
    }

    private function updateCase($caseData)
    {
        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $caseData['case'];
        $request = $caseData['request'];

        if($case->getScore() != $request['score'] && $request['score'] != null)
        {
            $case->setScore($request['score']);
            $this->handleScoreChange($caseData);
        }

        if($case->getSignifydStatus() != $request['status'] && $request['status'] != null)
        {
            $case->setSignifydStatus($request['status']);
            $this->handleStatusChange($caseData);
        }

        if($case->getGuarantee() != $request['guaranteeDisposition'] && $request['guaranteeDisposition'] != null)
        {
            $case->setGuarantee($request['guaranteeDisposition']);
            $this->handleGuaranteeChange($caseData);
        }

        $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
        $case->save();
    }

    private function handleScoreChange($caseData)
    {
        /** @var $order \Magento\Sales\Model\Order */
        $order = $caseData['order'];
        $threshHold = (int)$this->_coreConfig->getValue('signifyd/advanced/hold_orders_threshold');
        $holdBelowThreshold = $this->_coreConfig->getValue('signifyd/advanced/hold_orders');
        if($holdBelowThreshold && $caseData['request']['score'] <= $threshHold) {
            $order->hold();
        }
    }

    private function handleStatusChange($caseData)
    {
        /** @var $order \Magento\Sales\Model\Order */
        $order = $caseData['order'];
        $holdBelowThreshold = $this->_coreConfig->getValue('signifyd/advanced/hold_orders');
        if($holdBelowThreshold && $caseData['request']['reviewDisposition'] == 'FRAUDULENT') {
            $order->hold();
        } else if($holdBelowThreshold && $caseData['request']['reviewDisposition'] == 'GOOD') {
            $order->unhold();
        }
    }

    private function handleGuaranteeChange($caseData)
    {
        $negativeAction = $this->_coreConfig->getValue('signifyd/advanced/guarantee_negative_action');
        $positiveAction = $this->_coreConfig->getValue('signifyd/advanced/guarantee_positive_action');

        /** @var $order \Magento\Sales\Model\Order */
        $order = $caseData['order'];
        $request = $caseData['request'];
        if (isset($request['guaranteeDisposition'])) {
            if ($request['guaranteeDisposition'] == 'DECLINED' && $negativeAction != 'nothing') {
                if ($negativeAction == 'hold') {
                    $order->hold();
                } else if ($negativeAction == 'cancel') {
                    $order->cancel();
                }
            } else if ($this->_request ['guaranteeDisposition'] == 'APPROVED' && $positiveAction != 'nothing') {
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
        $this->_logger->info("Test log something $rawRequest $hash $topic");

        if ($this->_api->validWebhookRequest($rawRequest, $hash, $topic)) {
            $request = json_decode($rawRequest);
            $caseData = $this->initRequest($request, $topic);
            $this->updateCase($caseData);
        }
    }
}
