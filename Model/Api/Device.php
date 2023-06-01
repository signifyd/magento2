<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Helper\DeviceHelper;

class Device
{
    /**
     * @var RemoteAddress
     */
    protected $remoteAddress;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var DeviceHelper
     */
    protected $deviceHelper;

    /**
     * @var FingerprintFactory
     */
    protected $fingerprintFactory;

    /**
     * @param RemoteAddress $remoteAddress
     * @param ConfigHelper $configHelper
     * @param DeviceHelper $deviceHelper
     * @param FingerprintFactory $fingerprintFactory
     */
    public function __construct(
        RemoteAddress $remoteAddress,
        ConfigHelper $configHelper,
        DeviceHelper $deviceHelper,
        FingerprintFactory $fingerprintFactory
    ) {
        $this->remoteAddress = $remoteAddress;
        $this->configHelper = $configHelper;
        $this->deviceHelper = $deviceHelper;
        $this->fingerprintFactory = $fingerprintFactory;
    }

    /**
     * Construct a new Device object
     * @param $quoteId
     * @param $storeId
     * @param $order
     * @return array|null
     */
    public function __invoke($quoteId, $storeId, $order = null)
    {
        $filterIpd = isset($order) ?
            $this->getIPAddress($order) : $this->filterIp($this->remoteAddress->getRemoteAddress());

        if (isset($filterIpd) === false) {
            return null;
        }

        $fingerprint = $this->fingerprintFactory->create();
        $device = [];
        $device['clientIpAddress'] = $filterIpd;
        $device['sessionId'] = $this->deviceHelper->generateFingerprint($quoteId, $storeId);
        $device['fingerprint'] = $fingerprint();
        return $device;
    }

    /**
     * Getting the ip address of the order
     * @param Order $order
     * @return mixed
     */
    protected function getIPAddress(Order $order)
    {
        if ($order->getRemoteIp()) {
            if ($order->getXForwardedFor()) {
                return $this->filterIp($order->getXForwardedFor());
            }

            return $this->filterIp($order->getRemoteIp());
        }

        return $this->filterIp($this->remoteAddress->getRemoteAddress());
    }

    /**
     * Filter the ip address
     * @param $ip
     * @return mixed
     */
    protected function filterIp($ipString)
    {
        $matches = [];

        $pattern = '(([0-9]{1,3}(?:\.[0-9]{1,3}){3})|([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|' .
            '([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|' .
            '([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|' .
            '([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|' .
            '[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|' .
            'fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|' .
            '(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|' .
            '([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|' .
            '(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))';

        preg_match_all($pattern, $ipString, $matches);

        if (isset($matches[0]) && isset($matches[0][0])) {
            return $matches[0][0];
        }

        return null;
    }
}