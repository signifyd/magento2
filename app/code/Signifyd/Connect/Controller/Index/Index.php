<?php
namespace Signifyd\Connect\Controller\Index;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

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

    // TEMPORARY: Prefer passing this around rather than storing
    protected $_case;
    protected $_order;

    private function getApiKey()
    {
        return $this->_coreConfig->getValue('signifyd/general/key');
    }

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

    // TODO: This should mostly live in the SDK
    public function validRequest($request, $hash, $topic)
    {
        $check = base64_encode(hash_hmac('sha256', $request, $this->getApiKey(), true));

        if ($check == $hash) {
            return true;
        }

        else if ($topic == "cases/test"){
            // In the case that this is a webhook test, the encoding ABCDE is allowed
            $check = base64_encode(hash_hmac('sha256', $request, 'ABCDE', true));
            if ($check == $hash) {
                return true;
            }
        }

        return false;
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
    }

    public function execute()
    {
        $rawRequest = $this->getRawPost();
        $hash = $this->getHeader('X-SIGNIFYD-SEC-HMAC-SHA256');
        $topic = $this->getHeader('X-SIGNIFYD-TOPIC');
        $this->_logger->info("Test log something");
        if ($this->validRequest($rawRequest, $hash, $topic)) {
            $request = json_decode($rawRequest);
            $this->initRequest($request, $topic);
        }
    }
}
