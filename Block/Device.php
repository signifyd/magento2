<?php

namespace Signifyd\Connect\Block;

use \Magento\Checkout\Model\Session;
use \Magento\Framework\View\Element\Template\Context;
use \Signifyd\Connect\Helper\DeviceHelper;

class Device extends \Magento\Framework\View\Element\Template
{
    protected $deviceHelper;

    public function __construct(Context $context, Session $session, DeviceHelper $deviceHelper, array $data = [])
    {
        $this->quoteId = $session->getQuoteId();
        $this->deviceHelper = $deviceHelper;
        parent::__construct($context, $data);
    }

    public function getDeviceFingerprint()
    {
        return $this->deviceHelper->generateFingerprint($this->getQuoteId());
    }

    public function getQuoteId()
    {
        return empty($this->quoteId) ? false : $this->quoteId;
    }

    public function isEnabled()
    {
        if ($this->deviceHelper->isDeviceFingerprintEnabled()) {
            return $this->getQuoteId();
        } else {
            return false;
        }
    }
}