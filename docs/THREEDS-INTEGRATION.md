# ThreeDs integration

### Implementation

First it is necessary to find where the payment method ThreeDs response is received, then create a plugin/preference/observer and inject the class Signifyd\Connect\Model\ThreeDsIntegration. On the injected class, use the method setThreeDsData to store the data to be sent to Signifyd.

To set gateway data it is necessary to map the data according to the Signifyd documentation (https://docs.signifyd.com/#operation/Transaction in transactions > threeDsResult). Note: Before accessing array elements, assert that you're accessing an array to avoid fatal errors.

The final plugin/preference/observer class should look like the bellow one:

```php
<?php

namespace Signifyd\Connect\Plugin\Adyen\Payment\Helper;

use Signifyd\Connect\Model\ThreeDsIntegration;
use Adyen\Payment\Helper\PaymentResponseHandler as AdyenPaymentResponseHandler;

class PaymentResponseHandler
{
    /**
     * @var ThreeDsIntegration
     */
    protected $threeDsIntegration;

    /**
     * @param ThreeDsIntegration $threeDsIntegration
     */
    public function __construct(
        ThreeDsIntegration $threeDsIntegration
    ) {
        $this->threeDsIntegration = $threeDsIntegration;
    }

    public function beforeFormatPaymentResponse(
        AdyenPaymentResponseHandler $subject,
        $resultCode,
        $action = null,
        $additionalData = null
    ) {
        if (isset($additionalData) === false || is_array($additionalData) === false) {
            return;
        }

        if (isset($additionalData['threeDAuthenticated']) === false || $additionalData['threeDAuthenticated'] === 'false') {
            return;
        }

        $threeDsData = [];

        if (isset($additionalData['eci'])) {
            $threeDsData['eci'] = $additionalData['eci'];
        }

        if (isset($additionalData['cavv'])) {
            $threeDsData['cavv'] = $additionalData['cavv'];
        }

        if (isset($additionalData['threeDSVersion'])) {
            $threeDsData['version'] = $additionalData['threeDSVersion'];
        }

        if (isset($additionalData['threeDAuthenticatedResponse'])) {
            switch ($additionalData['threeDAuthenticatedResponse']) {
                case 'SOME_RETURN_CODE-001':
                    $threeDAuthenticatedResponse = 'AUTHENTICATION_SUCCESS';
                    break;

                case 'SOME_RETURN_CODE-002':
                    $threeDAuthenticatedResponse = 'AUTHENTICATION_UNAVAILABLE';
                    break;
            }

            $threeDsData['transStatus'] = $threeDAuthenticatedResponse;
        }

        if (isset($additionalData['dsTransID'])) {
            $threeDsData['dsTransId'] = $additionalData['dsTransID'];
        }

        $this->threeDsIntegration->setThreeDsData($threeDsData);

        return [$resultCode, $action, $additionalData];
    }
}
```
### Troubleshoot

If the message "Quote id not found" is displayed in the log file signifyd_connect.log, it means that the extension did not find the quote ID in the checkout session. In this case it's necessary to send the quote ID on the setThreeDsData method, as the second parameter.

```php
$this->threeDsIntegration->setThreeDsData($threeDsData, $quoteId);
```
