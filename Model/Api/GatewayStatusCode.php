<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Sales\Model\Order;
use Signifyd\Connect\Helper\PaymentStatusHelper;

class GatewayStatusCode
{
    /**
     * The gateway authorized or captured the payment
     */
    public const SUCCESS = 'SUCCESS';

    /**
     * The payment has not concluded yet, e.g. a 3DS challenge is in progress
     */
    public const PENDING = 'PENDING';

    /**
     * The gateway refused the payment
     */
    public const FAILURE = 'FAILURE';

    /**
     * The authorization process raised an error, no verdict was given by the gateway
     */
    public const ERROR = 'ERROR';

    /**
     * The order was canceled while the payment was still unresolved
     */
    public const CANCELLED = 'CANCELLED';

    /**
     * @var PaymentStatusHelper
     */
    public $paymentStatusHelper;

    /**
     * GatewayStatusCode construct.
     *
     * @param PaymentStatusHelper $paymentStatusHelper
     */
    public function __construct(
        PaymentStatusHelper $paymentStatusHelper
    ) {
        $this->paymentStatusHelper = $paymentStatusHelper;
    }

    /**
     * Derives the gateway status code from the current order and payment situation.
     *
     * The default is SUCCESS, so orders placed already authorized, which is the common case,
     * keep the payload they have always had.
     *
     * @param Order $order
     * @return string
     */
    public function __invoke(Order $order)
    {
        if ($this->paymentStatusHelper->isPaymentUnresolved($order) === false) {
            return self::SUCCESS;
        }

        $reportedStatusCode = $this->paymentStatusHelper->getGatewayStatusCode($order);

        if (isset($reportedStatusCode)) {
            return $reportedStatusCode;
        }

        if ($this->paymentStatusHelper->isOrderCanceled($order)) {
            return self::CANCELLED;
        }

        if ($this->paymentStatusHelper->isPaymentPending($order)) {
            return self::PENDING;
        }

        return self::SUCCESS;
    }
}
