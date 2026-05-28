<?php
/**
 * Copied and adapted from Magento 2.2.2 Magento\Braintree\Model\AvsEmsCodeMapper
 *
 * This version uses less classes and it is compatible with any Magento 2 version
 */
namespace Signifyd\Connect\Model\Payment\Braintree;

use Signifyd\Connect\Model\Payment\Base\AvsEmsCodeMapper as Base_AvsEmsCodeMapper;

/**
 * Processes AVS codes mapping from Braintree transaction.
 *
 * @see https://developers.braintreepayments.com/reference/response/transaction
 */
class AvsEmsCodeMapper extends Base_AvsEmsCodeMapper
{
    /**
     * @var string[]
     */
    public $allowedMethods = ['braintree'];

    /**
     * Gets payment AVS verification code.
     *
     * Braintree provides two separate AVS components. Returns an array with
     * 'addressMatchCode' (street) and 'zipMatchCode' (postal) for the API v3
     * verifications.avsResponse object.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array|null
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();

        if (empty($additionalInfo['avsStreetAddressResponseCode']) == false &&
            empty($additionalInfo['avsPostalCodeResponseCode']) == false
        ) {
            $avsStatus = [
                'addressMatchCode' => $additionalInfo['avsStreetAddressResponseCode'],
                'zipMatchCode'     => $additionalInfo['avsPostalCodeResponseCode']
            ];

            $message = 'AVS found on payment mapper: ' .
                $avsStatus['addressMatchCode'] . '/' . $avsStatus['zipMatchCode'];
            $this->logger->debug($message, ['entity' => $order]);

            return $avsStatus;
        }

        $this->logger->debug('AVS found on payment mapper: false', ['entity' => $order]);
        return parent::getPaymentData($order);
    }
}
