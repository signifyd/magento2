<?php

namespace Signifyd\Connect\Model\Payment\Stripe\Payments;

use Signifyd\Connect\Model\Payment\Base\AsyncChecker as BaseAsyncChecker;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Model\Casedata;

class AsyncChecker extends BaseAsyncChecker
{
    /**
     * @param Order $order
     * @param Casedata $case
     * @return bool|void
     */
    public function __invoke(Order $order, Casedata $case)
    {
        if ($case->getOrder()->getPayment()->getMethod() === 'stripe_payments' &&
            $case->getEntries('stripe_status') !== 'approved'
        ) {
            $this->logger->info(
                "CRON: case no: {$case->getOrderIncrement()}" .
                " will not be sent because the stripe hasn't approved it yet",
                ['entity' => $case]
            );
            return false;
        }
        return true;
    }
}
