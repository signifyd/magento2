<?php

namespace Signifyd\Connect\Model\Payment\AdyenHpp;

use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Model\Payment\Base\PaymentMethodBase;

class PaymentMethodMapper extends PaymentMethodBase
{
    /**
     * Get payment method from order method.
     *
     * @param Order $order
     * @return int|string
     */
    public function getPaymentMethodFromOrder(Order $order)
    {
        $paymentBrandCode = $order->getPayment()->getAdditionalInformation()['brand_code'];

        return $this->adyenHppMapping($paymentBrandCode, $order);
    }

    /**
     * Get payment method from quote method.
     *
     * @param Quote $quote
     * @return null
     */
    public function getPaymentMethodFromQuote(Quote $quote)
    {
        $paymentBrandCode = $quote->getPayment()->getAdditionalInformation('brand_code');

        return $this->adyenHppMapping($paymentBrandCode, $quote);
    }

    /**
     * Adyen hpp mapping method.
     *
     * @param mixed $paymentBrandCode
     * @param mixed $entity
     * @return int|string
     */
    public function adyenHppMapping($paymentBrandCode, $entity)
    {
        $paymentMethod = $entity->getPayment()->getMethod();

        if (is_string($paymentBrandCode) === false) {
            $this->logger->info('Adyen Hpp method code not found', ['entity' => $entity]);

            return $this->makePaymentMethod($paymentMethod);
        }

        $this->logger->info('Mapping for Adyen Hpp method code: ' . $paymentBrandCode, ['entity' => $entity]);

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

                $message = 'Payment method found on base mapper: ' . (empty($method) ? 'false' : $method);
                $this->logger->debug($message, ['entity' => $entity]);

                return $method;
        }

        $message = 'Payment method found on payment mapper: ' . $method;
        $this->logger->debug($message, ['entity' => $entity]);

        return $method;
    }
}
