# Transactions integration

Update Signifyd with Transaction data helps improve Signifyd risk analysis and, it's mandatory when using pre-auth guarantees.

On pre-auth guarantee, Signifyd provides a response about the transaction before the authorization and, it's important to hit Signifyd back with the final results for the transaction, so Signifid can be aware if there is an actual purchase and transaction for the case created at that moment.

For each payment method that want to integrate with the transaction endpoint, it is necessary to make some implementations in the extension

### Implementation of the payment method in the mappings

It is necessary to add the payment method to the mapped list in the etc/config.xml file:

```xml
<payment_methods_config>{"CREDIT_CARD":["payflow_link", "payflow_advanced", "authorizenet_acceptjs", "adyen_cc", "adyen_oneclick", "adyen_hpp", "braintree", "cybersource", "stripe_payments", "anet_creditcard", "authorizenet_directpost", "openpay_cards", "holacash", "stripe_payments"],"CHECK":["checkmo"]}</payment_methods_config>
```

Likewise, it is necessary to update the payment method mapping doc on docs/PAYMENT-MAPPING.md file:

```sql
INSERT INTO core_config_data (path, value) VALUES ('signifyd/general/payment_methods_config', '{"CREDIT_CARD":["payflow_link", "payflow_advanced", "authorizenet_acceptjs", "adyen_cc", "braintree", "cybersource", "stripe_payments", "anet_creditcard", "authorizenet_directpost", "openpay_cards", "holacash"],"CHECK":["checkmo"], "SIGNIFYD-PAYMENT-CODE": ["magento-payment-code"]}');
```

### Implementation of card data

The extension is already prepared to receive the following card fields: cardBin, holderName, cardLast4, cardExpiryMonth and cardExpiryYear.
But for that it is necessary to create the mapping directly in the JS of the payment method and map these fields.

First it is necessary to find where the payment method handles the card data, then create a mixin adding all possible card data in 'additional_data'.

The final mixin should look like the bellow one:

```js
define(function () {
    'use strict';
    var mixin = {
        getData: function (key) {
            var returnInformation = this._super();

            if (
                typeof this.creditCardExpYear() !== 'undefined' &&
                typeof this.creditCardExpMonth() !== 'undefined' &&
                typeof this.creditCardNumber() !== 'undefined'
            ) {
                var last4 = this.creditCardNumber().substr(-4);
                var bin = this.creditCardNumber().substr(0,6);

                returnInformation.additional_data.cardExpiryMonth = this.creditCardExpMonth();
                returnInformation.additional_data.cardExpiryYear = this.creditCardExpYear();
                returnInformation.additional_data.cardLast4 = last4;
                returnInformation.additional_data.cardBin = bin;
            }

            return returnInformation;
        }
    };
    return function (target) {
        return target.extend(mixin);
    };
});
```

### Implementation in case of failure

First it is necessary to find where the payment method response is received, then create a plugin/preference and inject the class Signifyd\Connect\Model\TransactionIntegration to set the gateway refused reason, gateway status message and use the method submitToTransactionApi to submit the data to Signifyd. 

Then it is necessary to map the payment method code according to the Signifyd documentation (https://docs.signifyd.com/#operation/Transaction in transactions > gatewayErrorCode). Note: Before accessing array elements, assert that you're accessing an array to avoid fatal errors.

The final plugin/preference class should look like the bellow one:

```php
<?php

namespace Signifyd\Connect\Plugin\Custom\Cards\Model;

use Custom\Cards\Model\Payment as CustomPayment;
use Signifyd\Connect\Model\TransactionIntegration;

class Payment
{
    /**
     * @var TransactionIntegration
     */
    protected $transactionIntegration;

    /**
     * CheckoutPaymentsDetailsHandler constructor.
     * @param TransactionIntegration $transactionIntegration
     */
    public function __construct(
        TransactionIntegration $transactionIntegration
    ) {
        $this->transactionIntegration = $transactionIntegration;
    }

    /**
    * @param CustomPayment $subject
    * @param $e
    * @return void|null
    */
    public function beforeError(CustomPayment $subject, $e)
    {
        //Mapping the error according to Signifyd doc
        switch ($e->getCode()) {
            case 'SOME_RETURN_CODE-001':
                $signifydReason = 'INVALID_NUMBER';
                break;

            case 'SOME_RETURN_CODE-002':
                $signifydReason = 'INVALID_EXPIRY_DATE';
                break;

            case 'SOME_RETURN_CODE-003':
                $signifydReason = 'TEST_CARD_DECLINE';
                break;
        }

        $this->transactionIntegration->setGatewayRefusedReason($signifydReason);
        $this->transactionIntegration->setGatewayStatusMessage($e->getDescription());
        $this->transactionIntegration->submitToTransactionApi();
    }
}
```