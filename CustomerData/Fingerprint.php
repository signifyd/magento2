<?php

namespace Signifyd\Connect\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Framework\DataObject;
use Magento\Checkout\Model\Session;
use Signifyd\Connect\Helper\DeviceHelper;
use Magento\Store\Model\StoreManagerInterface;

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
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * FingerprintSection constructor.
     * @param Session $session
     * @param DeviceHelper $deviceHelper
     * @param StoreManagerInterface $storeManagerInterface
     */
    public function __construct(
        Session $session,
        DeviceHelper $deviceHelper,
        StoreManagerInterface $storeManagerInterface
    ) {
        $this->quoteId = $session->getQuoteId();
        $this->deviceHelper = $deviceHelper;
        $this->storeManagerInterface = $storeManagerInterface;
    }

    /**
     * @return string
     */
    public function getDeviceFingerprint()
    {
        $storeId = $this->storeManagerInterface->getStore()->getId();
        return $this->deviceHelper->generateFingerprint($this->getQuoteId(), $storeId);
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
