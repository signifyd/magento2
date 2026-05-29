<?php

namespace Signifyd\Connect\Model\Api;

use Braintree\Exception;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\PaymentVerificationFactory;

class Verifications
{
    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var PaymentVerificationFactory
     */
    public $paymentVerificationFactory;

    /**
     * Verifications construct.
     *
     * @param Logger $logger
     * @param PaymentVerificationFactory $paymentVerificationFactory
     */
    public function __construct(
        Logger $logger,
        PaymentVerificationFactory $paymentVerificationFactory
    ) {
        $this->logger = $logger;
        $this->paymentVerificationFactory = $paymentVerificationFactory;
    }

    /**
     * Construct a new Verifications object
     *
     * @param Order $order
     * @return array
     */
    public function __invoke(Order $order)
    {
        $verifications = [];
        $avsData = $this->getAvsCode($order);

        if (is_array($avsData)) {
            $verifications['avsResponse'] = $avsData;
        } else {
            $verifications['avsResponseCode'] = $avsData;
        }

        $verifications['cvvResponseCode'] = $this->getCvvCode($order);
        return $verifications;
    }

    /**
     * Gets AVS code for order payment method.
     *
     * Returns a string (single raw code) for gateways that provide one AVS result,
     * or an array with 'addressMatchCode' and 'zipMatchCode' keys for gateways
     * that provide street and postal code checks separately.
     *
     * @param Order $order
     * @return string|array|null
     */
    public function getAvsCode(Order $order)
    {
        try {
            $avsAdapter = $this->paymentVerificationFactory->createPaymentAvs(
                $order->getPayment()->getMethod()
            );

            $this->logger->debug(
                'Getting AVS code using ' . get_class($avsAdapter),
                ['entity' => $order]
            );

            $avsCode = $avsAdapter->getData($order);

            if (isset($avsCode) === false || $avsCode === null) {
                return null;
            }

            if (is_array($avsCode)) {
                return $avsCode;
            }

            return trim(strtoupper((string) $avsCode));
        } catch (Exception $e) {
            $this->logger->error(
                'Error fetching AVS code: ' . $e->getMessage(),
                ['entity' => $order]
            );
            return null;
        }
    }

    /**
     * Gets CVV code for order payment method.
     *
     * @param Order $order
     * @return string
     */
    public function getCvvCode(Order $order)
    {
        try {
            $cvvAdapter = $this->paymentVerificationFactory->createPaymentCvv(
                $order->getPayment()->getMethod()
            );

            $this->logger->debug(
                'Getting CVV code using ' . get_class($cvvAdapter),
                ['entity' => $order]
            );

            $cvvCode = $cvvAdapter->getData($order);

            if (isset($cvvCode) === false) {
                return null;
            }

            return $cvvCode;
        } catch (Exception $e) {
            $this->logger->error(
                'Error fetching CVV code: ' . $e->getMessage(),
                ['entity' => $order]
            );
            return null;
        }
    }
}
