<?php

namespace Signifyd\Connect\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Framework\DataObject;
use Magento\Checkout\Model\Session;
use Signifyd\Connect\Helper\DeviceHelper;

/**
 * Class FingerprintSection
 * @package Signifyd\Connect\CustomerData
 */
class Fingerprint extends DataObject implements SectionSourceInterface
{
    /**
     * @var DeviceHelper
     */
    protected $deviceHelper;

    /**
     * @var
     */
    protected $quoteId;

    /**
     * FingerprintSection constructor.
     * @param Session $session
     * @param DeviceHelper $deviceHelper
     */
    public function __construct(Session $session, DeviceHelper $deviceHelper)
    {
        $this->quoteId = $session->getQuoteId();
        $this->deviceHelper = $deviceHelper;
    }

    /**
     * @return string
     */
    public function getDeviceFingerprint()
    {
        return $this->deviceHelper->generateFingerprint($this->getQuoteId());
    }

    /**
     * @return bool
     */
    public function getQuoteId()
    {
        return empty($this->quoteId) ? false : $this->quoteId;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        if ($this->deviceHelper->isDeviceFingerprintEnabled()) {
            return $this->getQuoteId();
        } else {
            return false;
        }
    }

    /**
     * @return array
     */
    public function getSectionData()
    {
        $data = ['dataOrderSessionId' => ''];

        if ($this->isEnabled()) {
            $data['dataOrderSessionId'] = $this->getDeviceFingerprint();
        }

        return $data;
    }
}