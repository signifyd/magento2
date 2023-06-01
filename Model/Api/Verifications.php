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
    protected $logger;

    /**
     * @var PaymentVerificationFactory
     */
    protected $paymentVerificationFactory;

    /**
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
     * @param Order $order
     * @return array
     */
    public function __invoke(Order $order)
    {
        $verifications = [];
        $verifications['avsResponseCode'] = $this->getAvsCode($order);
        $verifications['cvvResponseCode'] = $this->getCvvCode($order);
        return $verifications;
    }

    /**
     * Gets AVS code for order payment method.
     *
     * @param Order $order
     * @return string
     */
    public function getAvsCode(Order $order)
    {
        try {
            $avsAdapter = $this->paymentVerificationFactory->createPaymentAvs(
                $order->getPayment()->getMethod()
            );

            $this->logger->debug(
                'Getting AVS code using ' . get_class($avsAdapter), ['entity' => $order]
            );

            $avsCode = $avsAdapter->getData($order);

            if (isset($avsCode) === false) {
                return null;
            }

            $avsCode = trim(strtoupper($avsCode));

            if ($avsAdapter->validate($avsCode)) {
                return $avsCode;
            } else {
                return null;
            }
        } catch (Exception $e) {
            $this->logger->error(
                'Error fetching AVS code: ' . $e->getMessage(), ['entity' => $order]
            );
            return '';
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
                'Getting CVV code using ' . get_class($cvvAdapter), ['entity' => $order]
            );

            $cvvCode = $cvvAdapter->getData($order);

            if (isset($cvvCode) === false) {
                return null;
            }

            $cvvCode = trim(strtoupper($cvvCode));

            if ($cvvAdapter->validate($cvvCode)) {
                return $cvvCode;
            } else {
                return null;
            }
        } catch (Exception $e) {
            $this->logger->error(
                'Error fetching CVV code: ' . $e->getMessage(), ['entity' => $order]
            );
            return null;
        }
    }
}