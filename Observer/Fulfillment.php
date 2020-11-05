<?php

namespace Signifyd\Connect\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Signifyd\Connect\Helper\FulfillmentHelper;
use Signifyd\Connect\Logger\Logger;

class Fulfillment implements ObserverInterface
{
    /** @var FulfillmentHelper */
    protected $fulfillmentHelper;

    /** @var Registry */
    protected $registry;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Fulfillment constructor.
     * @param Registry $registry
     * @param FulfillmentHelper $fulfillmentHelper
     * @param Logger $logger
     */
    public function __construct(
        Registry $registry,
        FulfillmentHelper $fulfillmentHelper,
        Logger $logger
    ) {
        $this->registry = $registry;
        $this->fulfillmentHelper = $fulfillmentHelper;
        $this->logger = $logger;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Shipment\Track $track */
        $track = $observer->getEvent()->getTrack();

        $this->logger->info('Fulfillment event triggered for object ' . get_class($track));

        if ($track instanceof \Magento\Sales\Model\Order\Shipment\Track) {
            $shipment = $track->getShipment();

            $this->logger->info('Fulfillment shipment object ' . get_class($track->getShipment()));

            if ($shipment instanceof \Magento\Sales\Model\Order\Shipment) {
                // This observer can be called multiple times during a single shipment save
                // This registry entry is used to don't trigger fulfillment creation multiple times on a single save
                $registryKey = "signifyd_action_shipment_{$shipment->getId()}";

                if ($this->registry->registry($registryKey) == 1) {
                    $this->logger->info('Fulfillment will not proceed because registry key found: ' . $registryKey);
                    return;
                }

                $this->registry->register($registryKey, 1);

                $this->fulfillmentHelper->postFulfillmentToSignifyd($shipment);
            }
        }
    }
}
