[Signifyd Extension for Magento 2](../README.md) > Pass payment details for pre-auth policy

# Pass payment details - pre auth policy

## Overview

On the pre auth policy, Signifyd cases are created before the payment being submitted to the payment gateway. So, AVS code, CVV code and transaction ID are not available at all at this moment. But it is desirable to try to collect most of these information:

- Bin: credit card first 6 digits
- Holder name: The full name of the account holder as provided during checkout.
- Last4: credit card last 4 digits
- Expiry month
- Expiry year

In order to do that, it's needed to use some JavaScript code to collect most information as possible, store the information on hidden fields and submit them to Magento backend. Then the Signifyd extension will read those data and include them on the pre auth case creation request.

## Collect data strategy

If it is possible to read the credit card fields directly, then the JavaScript can read them after customer das typed the information and store the desired information on the hidden fields.

Although, most of the modern payment gateways do not allow any scripts to read the credit card information directly. If this is the case, then it's needed to investigate the payment gateway solution to figure out a way to collect most information as possible.

Usually, payment gateways use some JavaScript SDKs, if this is the case, a good approach is to look into the JavaScript SDK and look for some function which can be used to grab some of those information. E.g. some gateways create a card nonce, a token for the credit card information, and after the nonce generation a callback function it is called with the credit card nonce and some additional information, which can include some data necessary into this integration.

Or maybe a JavaScript of the payment method extension can also be used, combined or not with the gateway SDK.

An example of this integration can be seen on Signifyd GitHub. There is a built-in integration for Braintree solutions on which a mixin it is added to interfere on the original payment method JavaScript and collect bin and last4.

Mixins' declaration: https://github.com/signifyd/magento2/blob/master/view/base/requirejs-config.js
One of the mixins implementation: https://github.com/signifyd/magento2/blob/master/view/frontend/web/js/model/braintree-cc-mixin.js

## Data reception in magento beckend

The extension is prepared to receive these values through the request with the following structure. If one or more of these data is received the extension will add it to the case creation request.

```
"paymentMethod": {
     "additional_data": {
       "cardBin": "411111",
       "holderName": "J. Smith",
       "cardLast4": "1111",
       "cardExpiryMonth": "09",
       "cardExpiryYear": "2030"
     }
   }
```



### Pre auth for Braintree payment

In order to have Braintree integrated to the pre auth process, its needed to apply a patch to add the code modifications needed.

For Magento 2.3, use the file pre-auth-braintree-magento-2.3.patch

For Magento 2.4, use the file pre-auth-braintree-magento-2.4.patch

1. Copy the patch to the root directory of your Magento installation
```
cd [MAGENTO_ROOT]
cp vendor/signifyd/module-connect/Patch/pre-auth-braintree-magento-2.X.patch .
```

2. Apply the path
```
git apply pre-auth-braintree-magento-2.X.patch
```
