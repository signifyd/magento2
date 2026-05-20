<?php

namespace Signifyd\Connect\Model\PreAuth;

use Magento\Quote\Model\Quote;

interface CheckoutPaymentDetailsMapperInterface
{
    /**
     * Handle Checkout Payment
     *
     * @param array $checkoutPaymentDetails
     * @param array $dataArray
     * @param Quote $quote
     * @return array
     */
    public function handle(array $checkoutPaymentDetails, array $dataArray, Quote $quote): array;
}
