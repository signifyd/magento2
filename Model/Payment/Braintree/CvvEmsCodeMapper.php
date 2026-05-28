<?php
/**
 * Copied and adapted from Magento 2.2.2 Magento\Braintree\Model\CvvEmsCodeMapper
 *
 * This version uses less classes and it is compatible with any Magento 2 version
 */
namespace Signifyd\Connect\Model\Payment\Braintree;

use Signifyd\Connect\Model\Payment\Base\CvvEmsCodeMapper as Base_CvvEmsCodeMapper;

/**
 * Processes CVV codes mapping from Braintree transaction.
 *
 * @see https://developers.braintreepayments.com/reference/response/transaction
 */
class CvvEmsCodeMapper extends Base_CvvEmsCodeMapper
{
    /**
     * @var string[]
     */
    public $allowedMethods = ['braintree'];

    /**
     * Gets payment CVV verification code.
     *
     * Returns the raw Braintree CVV response code (M, N, U, I, S, A, B).
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string|null
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();
        $cvvStatus = null;

        if (empty($additionalInfo['cvvResponseCode']) == false) {
            $cvvStatus = $additionalInfo['cvvResponseCode'];
        }

        $message = 'CVV found on payment mapper: ' . (empty($cvvStatus) ? 'false' : $cvvStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($cvvStatus)) {
            $cvvStatus = parent::getPaymentData($order);
        }

        return $cvvStatus;
    }
}
