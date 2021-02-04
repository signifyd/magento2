<?php

namespace Signifyd\Connect\Observer\Order\Save;

use Magento\Framework\App\State as AppState;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Helper\ConfigHelper;

class Before implements ObserverInterface
{
    /**
     * @var Logger;
     */
    protected $logger;

    /**
     * @var AppState
     */
    protected $appState;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * Before constructor.
     * @param Logger $loger
     * @param AppState $appState
     * @param ConfigHelper $configHelper
     * @param StoreManagerInterface $storeManager
     * @param RequestInterface $request
     */
    public function __construct(
        Logger $loger,
        AppState $appState,
        ConfigHelper $configHelper,
        StoreManagerInterface $storeManager,
        RequestInterface $request
    ) {
        $this->logger = $loger;
        $this->appState = $appState;
        $this->configHelper = $configHelper;
        $this->storeManager = $storeManager;
        $this->request = $request;
    }

    /**
     * @param Observer $observer
     * @param bool $checkOwnEventsMethods
     */
    public function execute(Observer $observer, $checkOwnEventsMethods = true)
    {
        try {
            /** @var $order Order */
            $order = $observer->getEvent()->getOrder();

            if (!is_object($order)) {
                return;
            }

            if ($this->configHelper->isEnabled($order) == false) {
                return;
            }

            // Fix for Magento bug https://github.com/magento/magento2/issues/7227
            // x_forwarded_for should be copied from quote, but quote does not have the field on database
            if (empty($order->getData('x_forwarded_for')) && is_object($this->request)) {
                $xForwardIp = $this->request->getServer('HTTP_X_FORWARDED_FOR');

                if (empty($xForwardIp) == false) {
                    $order->setData('x_forwarded_for', $xForwardIp);
                }
            }
        } catch (\Exception $ex) {
            $context = [];

            if (isset($order) && $order instanceof Order) {
                $context['entity'] = $order;
            }

            $this->logger->error($ex->getMessage(), $context);
        }
    }
}
