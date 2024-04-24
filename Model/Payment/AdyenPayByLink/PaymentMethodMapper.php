<?php

namespace Signifyd\Connect\Model\Payment\AdyenPayByLink;

use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Model\Payment\Base\PaymentMethodBase;

class PaymentMethodMapper extends PaymentMethodBase
{
    /**
     * @var string[]
     */
    public $creditCard = ['amex', 'cup', 'discover', 'jcb', 'mc', 'visa'];

    /**
     * @param Order $order
     * @return int|string
     */
    public function getPaymentMethodFromOrder(Order $order)
    {
        $payByLinkMethod = $order->getPayment()->getAdditionalInformation('payment_method');

        return $this->adyenPayByLinkMapping($payByLinkMethod, $order);
    }

    /**
     * @param Quote $quote
     * @return null
     */
    public function getPaymentMethodFromQuote(Quote $quote)
    {
        $payByLinkMethod = $quote->getPayment()->getAdditionalInformation('payment_method');

        return $this->adyenPayByLinkMapping($payByLinkMethod, $quote);
    }

    public function adyenPayByLinkMapping($payByLinkMethod, $entity)
    {
        $paymentMethod = $entity->getPayment()->getMethod();

        if (isset($payByLinkMethod) === false) {
            $this->logger->info('Adyen Pay By Link method code not found', ['entity' => $entity]);

            return $this->makePaymentMethod($paymentMethod);
        }

        $this->logger->info('Mapping for Adyen Pay By Link method code: ' . $payByLinkMethod, ['entity' => $entity]);

        if (strpos($payByLinkMethod, 'amazonpay')) {
            $method = 'AMAZON_PAYMENTS';
        } elseif (strpos($payByLinkMethod, 'googlepay')) {
            $method = 'GOOGLE_PAY';
        } elseif (strpos($payByLinkMethod, 'applepay')) {
            $method = 'APPLE_PAY';
        } elseif (strpos($payByLinkMethod, 'paypal')) {
            $method = 'PAYPAL_ACCOUNT';
        } elseif (strpos($payByLinkMethod, 'debit')) {
            $method = 'DEBIT_CARD';
        } elseif (strpos($payByLinkMethod, 'credit') || in_array($payByLinkMethod, $this->creditCard)) {
            $method = 'CREDIT_CARD';
        } elseif (strpos($payByLinkMethod, 'prepaid')) {
            $method = 'PREPAID_CARD';
        } else {
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
