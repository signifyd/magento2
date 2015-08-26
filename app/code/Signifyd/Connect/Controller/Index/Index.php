<?php
namespace Signifyd\Connect\Controller\Index;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;
use Signifyd\Connect\Lib\SDK\core\SignifydAPI;
use Signifyd\Connect\Lib\SDK\core\SignifydSettings;

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
     * @var SignifydAPI
     */
    protected $_api;

    // TEMPORARY: Prefer passing this around rather than storing
    protected $_case;
    protected $_order;

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

    // TODO: Portions of this should live in the SDK
    public function initRequest($request, $topic)
    {
        // For the webhook test, all of the request data will be invalid
        if ($topic == "cases/test") return;
    }

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->_coreConfig = $scopeConfig;
        $this->_logger = $logger;

        try {
            $settings = new SignifydSettings();
            $settings->apiKey = $scopeConfig->getValue('signifyd/general/key');
            if(!$settings->apiKey)
            {
                $settings->apiKey = "ABCDE";
            }
            $settings->logInfo = true;
            $this->_api = new SignifydAPI($settings);
            $this->_logger->info(json_encode($settings));
        } catch (\Exception $e) {
            $this->_logger->error($e);
        }
    }

    public function execute()
    {
        $rawRequest = $this->getRawPost();
        $hash = $this->getHeader('X-SIGNIFYD-SEC-HMAC-SHA256');
        $topic = $this->getHeader('X-SIGNIFYD-TOPIC');
        $this->_logger->info("Test log something");
        if ($this->_api->validWebhookRequest($rawRequest, $hash, $topic)) {
            $request = json_decode($rawRequest);
            $this->initRequest($request, $topic);
        }
    }
}
