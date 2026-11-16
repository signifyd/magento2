<?php

namespace Signifyd\Connect\Model\PreAuth;

use Magento\Quote\Model\Quote;

interface QuotePaymentDetailsMapperInterface
{
    /**
     * Handle Checkout Payment stored on the quote payment
     *
     * @param array $checkoutPaymentDetails
     * @param Quote $quote
     * @return array
     */
    public function handleQuotePayment(array $checkoutPaymentDetails, Quote $quote): array;
}
