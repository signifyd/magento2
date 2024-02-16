<?php

namespace Signifyd\Connect\Observer\Order;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ProcessCron\Reroute as ProcessCronReroute;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\RerouteFactory;
use Signifyd\Connect\Model\ResourceModel\Reroute as RerouteResourceModel;

class SalesOrderAddressSave implements ObserverInterface
{
    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    public $casedataResourceModel;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var ProcessCronReroute
     */
    public $processCronReroute;

    /**
     * @var RerouteFactory
     */
    public $rerouteFactory;

    /**
     * @var RerouteResourceModel
     */
    public $rerouteResourceModel;

    public function __construct(
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        ConfigHelper $configHelper,
        Logger $logger,
        ProcessCronReroute $processCronReroute,
        RerouteFactory $rerouteFactory,
        RerouteResourceModel $rerouteResourceModel
    ) {
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->processCronReroute = $processCronReroute;
        $this->rerouteFactory = $rerouteFactory;
        $this->rerouteResourceModel = $rerouteResourceModel;
    }

    public function execute(Observer $observer)
    {
        try {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $observer->getEvent()->getAddress()->getOrder();

            if (!is_object($order)) {
                return;
            }

            if ($order->hasShipments()) {
                return;
            }

            if ($this->configHelper->isEnabled($order) == false) {
                return;
            }

            $orderId = $order->getId();
            $case = $this->casedataFactory->create();
            $this->casedataResourceModel->load($case, $orderId, 'order_id');

            if ($case->isEmpty()) {
                return;
            }

            /** @var \Signifyd\Connect\Model\Reroute $reroute */
            $reroute = $this->rerouteFactory->create();
            $this->rerouteResourceModel->load($reroute, $orderId, 'order_id');

            if ($reroute->isEmpty() === false) {
                $this->rerouteResourceModel->delete($reroute);
                $reroute = $this->rerouteFactory->create();
            }

            $reroute->setOrderId($orderId);
            $this->rerouteResourceModel->save($reroute);
            ($this->processCronReroute)($reroute);
        } catch (\Exception $e) {
            $this->logger->info('Failed to update case');
        }
    }
}
