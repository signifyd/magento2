<?php

namespace Signifyd\Connect\Model\Payment\Stripe\Payments;

use Signifyd\Connect\Model\Payment\Base\AsyncChecker as BaseAsyncChecker;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\Api\SaleOrderFactory;
use Signifyd\Connect\Logger\Logger;

class AsyncChecker extends BaseAsyncChecker
{
    /**
     * @var CvvEmsCodeMapper
     */
    public $cvvEmsCodeMapper;

    /**
     * @var SaleOrderFactory
     */
    public $saleOrderFactory;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @param CvvEmsCodeMapper $cvvEmsCodeMapper
     * @param SaleOrderFactory $saleOrderFactory
     * @param Logger $logger
     */
    public function __construct(
        CvvEmsCodeMapper $cvvEmsCodeMapper,
        SaleOrderFactory $saleOrderFactory,
        Logger $logger
    ) {
        parent::__construct($logger, $saleOrderFactory);
        $this->cvvEmsCodeMapper = $cvvEmsCodeMapper;
    }
    /**
     * Invoke method.
     *
     * @param Order $order
     * @param Casedata $case
     * @return bool|void
     */
    public function __invoke(Order $order, Casedata $case)
    {
        $cvvCode = $this->cvvEmsCodeMapper->getData($order);
        $retries = $case->getData('retries');

        if ($this->isModuleVersionAtLeast340() === false) {
            if (($retries >= 5 ||
                $case->getEntries('stripe_status') === 'approved')
            ) {
                return true;
            } else {
                $this->logger->info(
                    "CRON: case no: {$case->getOrderIncrement()}" .
                    " will not be sent because the stripe hasn't approved it yet",
                    ['entity' => $case]
                );
                return false;
            }
        } else {
            if ($retries >= 5 || isset($cvvCode)) {
                return true;
            } else {
                $this->logger->info(
                    "CRON: case no: {$case->getOrderIncrement()}" .
                    " will not be sent because the CVV was not collected.",
                    ['entity' => $case]
                );
                return false;
            }
        }
    }

    /**
     * Get async payment methods from store configs
     *
     * @param mixed $paymentMethod
     * @return bool
     */
    public function isModuleVersionAtLeast340()
    {
        try {
            $stripeVersion = \StripeIntegration\Payments\Model\Config::$moduleVersion;

            return version_compare($stripeVersion, '3.4.0') >= 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
