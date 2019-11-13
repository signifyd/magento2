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
    protected $allowedMethods = ['braintree'];

    /**
     * List of mapping AVS codes
     *
     * Keys are concatenation of ZIP (avsPostalCodeResponseCode) and Street (avsStreetAddressResponseCode) codes
     *
     * @var array
     */
    private static $avsMap = [
        'MM' => 'Y',
        'MN' => 'Z',
        'MU' => 'Z',
        'MI' => 'Z',
        'NM' => 'A',
        'NN' => 'N',
        'NU' => 'N',
        'NI' => 'N',
        'UU' => 'U',
        'II' => 'U',
        'AA' => 'U'
    ];

    /**
     * Gets payment AVS verification code.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();

        if (empty($additionalInfo['avsPostalCodeResponseCode']) == false &&
            empty($additionalInfo['avsStreetAddressResponseCode']) == false
        ) {
            $zipCode = $additionalInfo['avsPostalCodeResponseCode'];
            $streetCode = $additionalInfo['avsStreetAddressResponseCode'];
            $key = $zipCode . $streetCode;

            if (isset(self::$avsMap[$key]) && $this->validate(self::$avsMap[$key])) {
                $avsStatus = self::$avsMap[$key];
            }
        }

        $message = 'AVS found on payment mapper: ' . (empty($avsStatus) ? 'false' : $avsStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($avsStatus)) {
            $avsStatus = parent::getPaymentData($order);
        }
        
        return $avsStatus;
    }
}
