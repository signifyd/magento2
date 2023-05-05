<?php

namespace Signifyd\Connect\Model\Stripe;

use Magento\Framework\ObjectManagerInterface;

class ReInit
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \StripeIntegration\Payments\Model\Config
     */
    protected $stripeConfig;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * On background tasks Stripe must be reinitialized
     *
     * @param \Magento\Sales\Model\Order $order
     * @return void
     */
    public function __invoke(\Magento\Sales\Model\Order $order)
    {
        if (class_exists(\StripeIntegration\Payments\Model\Config::class) === false) {
            return;
        }

        if ($this->stripeConfig === null) {
            $this->stripeConfig = $this->objectManager->get(\StripeIntegration\Payments\Model\Config::class);
        }

        if (version_compare(\StripeIntegration\Payments\Model\Config::$moduleVersion, '1.8.8') >= 0 &&
            method_exists($this->stripeConfig, 'reInitStripe')) {
            $this->stripeConfig->reInitStripe($order->getStoreId(), $order->getBaseCurrencyCode(), null);
        }
    }
}