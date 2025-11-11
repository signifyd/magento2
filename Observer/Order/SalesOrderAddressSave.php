<?php

namespace Signifyd\Connect\Observer\Order;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ProcessCron\Reroute as ProcessCronReroute;
use Signifyd\Connect\Model\RerouteFactory;
use Signifyd\Connect\Model\ResourceModel\Reroute as RerouteResourceModel;

class SalesOrderAddressSave implements ObserverInterface
{
    /**
     * @var CasedataRepositoryInterface
     */
    public $casedataRepository;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

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

    /**
     * SalesOrderAddressSave method.
     *
     * @param CasedataRepositoryInterface $casedataRepository
     * @param CasedataFactory $casedataFactory
     * @param ConfigHelper $configHelper
     * @param Logger $logger
     * @param ProcessCronReroute $processCronReroute
     * @param RerouteFactory $rerouteFactory
     * @param RerouteResourceModel $rerouteResourceModel
     */
    public function __construct(
        CasedataRepositoryInterface $casedataRepository,
        CasedataFactory $casedataFactory,
        ConfigHelper $configHelper,
        Logger $logger,
        ProcessCronReroute $processCronReroute,
        RerouteFactory $rerouteFactory,
        RerouteResourceModel $rerouteResourceModel
    ) {
        $this->casedataRepository = $casedataRepository;
        $this->casedataFactory = $casedataFactory;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->processCronReroute = $processCronReroute;
        $this->rerouteFactory = $rerouteFactory;
        $this->rerouteResourceModel = $rerouteResourceModel;
    }

    /**
     * Execute method.
     *
     * @param Observer $observer
     * @return void
     */
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
            $case = $this->casedataRepository->getByOrderId($orderId);

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
