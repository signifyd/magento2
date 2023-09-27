<?php

namespace Signifyd\Connect\Model\Payment\Stripe\Payments;

use Signifyd\Connect\Model\Payment\Base\AsyncChecker as BaseAsyncChecker;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Model\Casedata;

class AsyncChecker extends BaseAsyncChecker
{
    public function __invoke(Order $order,Casedata $case)
    {
        $orderId = $order->getId();
        $case = $this->casedataFactory->create();
        $this->casedataResourceModel->load($case, $orderId, 'order_id');

        if ($case->getOrder()->getPayment()->getMethod() === 'stripe_payments' &&
            $case->getEntries('stripe_status') !== 'approved'
        ) {
            $this->logger->info(
                "CRON: case no: {$case->getOrderIncrement()}" .
                " will not be sent because the stripe hasn't approved it yet",
                ['entity' => $case]
            );
            return false; // Assuming you want to stop further processing if Stripe hasn't approved
        }

        // Call parent (base) class's __invoke method to perform base checks.
        return parent::__invoke($order, $case);
    }
}
