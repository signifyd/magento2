<?php

namespace Signifyd\Connect\Model\Api\CaseData\Transactions\PaymentMethod;

use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class AdyenHpp extends Base
{
    /**
     * @param Order $entity
     * @return int|string
     */
    public function getPaymentMethodFromOrder(Order $order)
    {
        $paymentBrandCode = $order->getPayment()->getAdditionalInformation('brand_code');

        return $this->adyenHppMapping($paymentBrandCode, $order->getPayment()->getMethod());
    }

    /**
     * @param Quote $entity
     * @return null
     */
    public function getPaymentMethodFromQuote(Quote $quote)
    {
        $paymentBrandCode = $quote->getPayment()->getAdditionalInformation('brand_code');

        return $this->adyenHppMapping($paymentBrandCode, $quote->getPayment()->getMethod());
    }

    public function adyenHppMapping($paymentBrandCode, $paymentMethod)
    {
        if (is_string($paymentBrandCode) === false) {
            $this->logger->info('Adyen Hpp method code not found');

            return $this->makePaymentMethod($paymentMethod);
        }

        $this->logger->info(
            'Mapping for Adyen Hpp method code: ' .
            $paymentBrandCode
        );

        switch ($paymentBrandCode) {
            case 'googlepay':
            case 'paywithgoogle':
                $method = 'GOOGLE_PAY';
                break;

            case 'applepay':
            case 'paywithapple':
                $method = 'APPLE_PAY';
                break;

            case 'paypal':
                $method = 'PAYPAL_ACCOUNT';
                break;

            case 'amazonpay':
                $method = 'AMAZON_PAYMENTS';
                break;

            default:
                $method = $this->makePaymentMethod($paymentMethod);
        }

        return $method;
    }
}