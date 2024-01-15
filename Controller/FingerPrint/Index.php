<?php

namespace Signifyd\Connect\Controller\FingerPrint;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\SignifydFingerprint;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var JsonFactory
     */
    protected $jsonResponseFactory;

    /**
     * @var SignifydFingerprint
     */
    protected $signifydFingerprint;

    /**
     * @param Context $context
     * @param Logger $logger
     * @param JsonFactory $jsonResponseFactory
     * @param SignifydFingerprint $signifydFingerprint
     */
    public function __construct(
        Context $context,
        Logger $logger,
        JsonFactory $jsonResponseFactory,
        SignifydFingerprint $signifydFingerprint
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->jsonResponseFactory = $jsonResponseFactory;
        $this->signifydFingerprint = $signifydFingerprint;
    }

    public function execute()
    {
        try {
            $fingerprintError = $this->_request->getParam('fingerprint_error');

            if (isset($fingerprintError) === false) {
                return $this->jsonResponseFactory->create()->setData([
                    'success' => false
                ]);
            }

            $this->logger->info("Adding fingerprint error: " . $fingerprintError);

            $this->signifydFingerprint->addFingerPrintLog($fingerprintError);

            return $this->jsonResponseFactory->create()->setData([
                'success' => true
            ]);
        } catch (\Exception $e) {
            $this->logger->debug('Failed to create fingerprint log ' . $e->getMessage());

            return $this->jsonResponseFactory->create()->setData([
                'success' => false
            ]);
        } catch (\Error $e) {
            $this->logger->debug('Failed to create fingerprint log ' . $e->getMessage());

            return $this->jsonResponseFactory->create()->setData([
                'success' => false
            ]);
        }
    }
}