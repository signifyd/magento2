<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\Api\SaleOrderFactory;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Api\AsyncCheckerInterface;

class AsyncChecker implements AsyncCheckerInterface
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var SaleOrderFactory
     */
    protected $saleOrderFactory;

    /**
     * @param Logger $logger
     * @param SaleOrderFactory $saleOrderFactory
     */
    public function __construct(
        Logger                     $logger,
        SaleOrderFactory           $saleOrderFactory
    ) {
        $this->logger = $logger;
        $this->saleOrderFactory = $saleOrderFactory;
    }

    /**
     * @param Order $order
     * @param Casedata $case
     * @return bool|void
     */
    public function __invoke(Order $order, Casedata $case)
    {
        try {
            $order->setData('origin_store_code', $case->getData('origin_store_code'));
            $saleOrder = $this->saleOrderFactory->create();
            $caseModel = $saleOrder($order);
            $avsCode = $caseModel['transactions'][0]['verifications']['avsResponseCode'];
            $cvvCode = $caseModel['transactions'][0]['verifications']['cvvResponseCode'];
            $retries = $case->getData('retries');

            if ($retries >= 5 || empty($avsCode) === false && empty($cvvCode) === false) {
                return true;
            }else {
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error(
                "There was a problem loading the order."
                . $e->getMessage()
            );
        }
    }
}
