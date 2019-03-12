<?php

namespace Signifyd\Connect\Model\Payment;

use Magento\Framework\Registry;
use Signifyd\Connect\Api\PaymentVerificationInterface;
use Signifyd\Connect\Helper\LogHelper;

abstract class DataMapper implements PaymentVerificationInterface
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * List of payment methods codes
     * @var array
     */
    protected $allowedMethods = array();

    /**
     * Flag to prevent accidental loop for getCode/getData calls
     * @var bool
     */
    protected $getDataCalled = false;

    /**
     * @var LogHelper
     */
    protected $logHelper;

    /**
     * Payflowlink constructor.
     * @param Registry $registry
     */
    public function __construct(
        Registry $registry,
        LogHelper $logHelper
    )
    {
        $this->registry = $registry;
        $this->logHelper = $logHelper;
    }

    /**
     * @param null $key
     * @return bool|mixed
     */
    public function getSignifydPaymentData($key = null)
    {
        $paymentData = $this->registry->registry('signifyd_payment_data');

        if (empty($key)) {
            return $paymentData;
        }

        if (is_array($paymentData)) {
            if (isset($paymentData[$key])) {
                return $paymentData[$key];
            } elseif (isset($paymentData[strtolower($key)])) {
                return $paymentData[strtolower($key)];
            } elseif (isset($paymentData[strtoupper($key)])) {
                return $paymentData[strtoupper($key)];
            }
        }

        return false;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return bool
     */
    public function checkMethod(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        if (empty($this->allowedMethods) || in_array($orderPayment->getMethod(), $this->allowedMethods)) {
            return true;
        } else {
            throw new \InvalidArgumentException(
                'The "' . $orderPayment->getMethod() . '" it is not supported by ' . get_class($this) . ' mapper.'
            );
        }
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return string
     */
    public final function getCode(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        if ($this->getDataCalled) {
            return null;
        } else {
            return $this->getData($orderPayment);
        }
    }

    /**
     * This method must be called to retrieve data. Use getPaymentMethod to actual retrieve data from payment method\
     * on extending classes
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return string
     */
    public final function getData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $this->checkMethod($orderPayment);
        return $this->getPaymentData($orderPayment);
    }
}
