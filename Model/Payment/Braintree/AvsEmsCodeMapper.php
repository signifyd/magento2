<?php
/**
 * Copied and adapted from Magento 2.2.2 Magento\Braintree\Model\AvsEmsCodeMapper
 *
 * This version uses less classes and it is compatible with any Magento 2 version
 */
namespace Signifyd\Connect\Model\Payment\Braintree;

use Signifyd\Connect\Model\Payment\Base\AvsEmsCodeMapper as Base_AvsEmsCodeMapper;

/**
 * Processes AVS codes mapping from Braintree transaction to
 * electronic merchant systems standard.
 *
 * @see https://developers.braintreepayments.com/reference/response/transaction
 * @see http://www.emsecommerce.net/avs_cvv2_response_codes.htm
 */
class AvsEmsCodeMapper extends Base_AvsEmsCodeMapper
{
    protected $allowedMethods = array('braintree');

    /**
     * List of mapping AVS codes
     *
     * @var array
     */
    private static $avsMap = [
        'MM' => 'Y',
        'NM' => 'A',
        'MN' => 'Z',
        'NN' => 'N',
        'UU' => 'U',
        'II' => 'U',
        'AA' => 'E'
    ];

    /**
     * Gets payment AVS verification code.
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return string
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $additionalInfo = $orderPayment->getAdditionalInformation();
        if (empty($additionalInfo['avsPostalCodeResponseCode']) ||
            empty($additionalInfo['avsStreetAddressResponseCode'])
        ) {
            return parent::getPaymentData($orderPayment);
        }

        $streetCode = $additionalInfo['avsStreetAddressResponseCode'];
        $zipCode = $additionalInfo['avsPostalCodeResponseCode'];
        $key = $zipCode . $streetCode;

        if (isset(self::$avsMap[$key]) && $this->validate(self::$avsMap[$key])) {
            return self::$avsMap[$key];
        } else {
            return parent::getPaymentData($orderPayment);
        }
    }
}
