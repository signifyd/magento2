<?php

namespace Signifyd\Connect\Model\Payment\Stripe\Payments;

trait MapperTrait
{
    /**
     * Get charge method.
     *
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
            $charge = $this->registry->getData($registryKey);

            if (is_object($charge)) {
                return $charge;
            } else {
                $this->logger->debug(
                    'No Stripe charge on registry, fetching from Stripe API',
                    ['entity' => $order]
                );

                $lastTransactionId = $order->getPayment()->getLastTransId();

                if (empty($lastTransactionId)) {
                    return false;
                }

                if (class_exists(\StripeIntegration\Payments\Model\Config::class)) {
                    $stripeClient = $this->objectManagerInterface
                        ->get(\StripeIntegration\Payments\Model\Config::class)->getStripeClient();
                }

                $paymentIntent = $stripeClient->paymentIntents->retrieve($lastTransactionId, []);

                if (is_object($paymentIntent) &&
                    isset($paymentIntent->latest_charge)
                ) {
                    $charge = $stripeClient->charges->retrieve($paymentIntent->latest_charge, []);

                    if (is_object($charge)) {
                        $this->registry->setData($registryKey, $charge);

                        return $charge;
                    } else {
                        return false;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug('There was a problem getting charge from Stripe API: ' . $e->getMessage());
        }

        return false;
    }
}
