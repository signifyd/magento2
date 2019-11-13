<?php

namespace Signifyd\Connect\Model\Payment\Stripe\Payments;

trait MapperTrait
{
    /**
     * @param \Magento\Sales\Model\Order $order
     * @return bool|\Stripe\Charge
     */
    public function getCharge(\Magento\Sales\Model\Order $order)
    {
        try {
            if ($order->isEmpty()) {
                return false;
            }

            $registryKey = "signify_stripe_payment_charge_{$order->getId()}";
            $charge = $this->registry->registry($registryKey);

            if (is_object($charge)) {
                return $charge;
            } else {
                $this->logger->debug('No Stripe charge on registry, fetching from Strupe API', ['entity' => $order]);

                $lastTransactionId = $order->getPayment()->getLastTransId();

                if (empty($lastTransactionId)) {
                    return false;
                }

                if (class_exists('\Stripe\PaymentIntent') == false) {
                    return false;
                }

                $customCurlClient = false;

                if (class_exists(\Stripe\HttpClient\CurlClient::class) &&
                    is_callable([\Stripe\ApiRequestor::class, 'setHttpClient'])
                ) {
                    $curl = new \Stripe\HttpClient\CurlClient();
                    $curl->setTimeout(2);
                    $curl->setConnectTimeout(1);
                    \Stripe\ApiRequestor::setHttpClient($curl);

                    $customCurlClient = true;
                }

                $paymentIntent = \Stripe\PaymentIntent::retrieve($lastTransactionId);

                if ($customCurlClient == true) {
                    \Stripe\ApiRequestor::setHttpClient(null);
                }

                if (is_object($paymentIntent) &&
                    is_object($paymentIntent->charges) &&
                    is_array($paymentIntent->charges->data)
                ) {
                    $charge = array_pop($paymentIntent->charges->data);

                    if (is_object($charge) == false) {
                        return false;
                    }

                    $this->registry->register($registryKey, $charge);
                    return $charge;
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug('There was a problem getting charge from Stripe API: ' . $e->getMessage());
        }

        return false;
    }
}
