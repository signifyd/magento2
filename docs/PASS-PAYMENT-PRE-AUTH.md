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

### Pre auth for AuthorizeNet payment

In order to have AuthorizeNet integrated to the pre auth process, it is needed to apply a patch to add the code modifications needed.

1. Copy the patch to the root directory of your Magento installation
```
cd [MAGENTO_ROOT]
cp vendor/signifyd/module-connect/Patch/pre-auth-authorizenet.patch .
```

2. Apply the path
```
git apply pre-auth-authorizenet.patch
```

## Compatible methods

### Adyen
#### Link to the extension https://github.com/Adyen/adyen-magento2
#### Tested on 9.5.3

- Call transaction API on failure: yes
- Payment data available:
    - Bin: yes
    - Last4: yes
    - Expiry date: no
    - Cardholder name: no

### Adyen One-click (saved cards)
#### Link to the extension https://github.com/Adyen/adyen-magento2
#### Tested on 9.5.3

- Call transaction API on failure: yes
- Payment data available:
    - Bin: yes
    - Last4: yes
    - Expiry date: yes
    - Cardholder name: no

### Braintree
#### Magento built in
#### Tested on 4.5.0

- Call transaction API on failure: yes
- Payment data available:
    - Bin: yes
    - Last4: yes
    - Expiry date: no
    - Cardholder name: no

### Braintree on Hyvä Checkout
#### Magento built in + hyva-themes/magento2-hyva-checkout-braintree Hyvä Compatibility module
#### Tested on Braintree module 4.6.1-p5
#### Braintree Hyvä compatibility module 1.1.0

- Call transaction API on failure: yes
- Payment data available:
    - Bin: yes
    - Last4: yes
    - Expiry date: yes
    - Cardholder name: no

### OpenPay
#### Link to the extension https://github.com/open-pay/openpay-magento2-cards
#### Tested on 2.3.0

- Call transaction API on failure: yes
- Payment data available:
    - Bin: yes
    - Last4: yes
    - Expiry date: yes
    - Cardholder name: no

### Stripe
#### Link to the extension https://commercemarketplace.adobe.com/stripe-stripe-payments.html
#### Tested on 3.5.16

> [!IMPORTANT]
> Stripe is compatible with pre auth, however it's not possible to collect any payment data

- Call transaction API on failure: yes
- Payment data available:
    - Bin: no
    - Last4: no
    - Expiry date: no
    - Cardholder name: no

### Authorize.net ParadoxLabs
#### Link to the extension https://commercemarketplace.adobe.com/paradoxlabs-authnetcim.html
#### Tested on 5.0.1

- Call transaction API on failure: yes
- Payment data available:
  - Bin: yes (not available for saved cards)
  - Last4: yes
  - Expiry date: yes
  - Cardholder name: yes

### Authorize.net Rootways
#### Link to the extension https://www.rootways.com/magento-2-authorize-net-cim-extension
#### Tested on 3.0.1

- Call transaction API on failure: yes
- Payment data available:
  - Bin: yes (not available for saved cards)
  - Last4: yes
  - Expiry date: yes
  - Cardholder name: no

### Mercado Pago - Custom Checkout
#### Link to the extension https://commercemarketplace.adobe.com/mercadopago-adb-payment.html
#### Tested on 3.19.0

- Call transaction API on failure: yes
- Payment data available:
  - Bin: no
  - Last4: no
  - Expiry date: no
  - Cardholder name: no

### Cybersource (Flex Microform)
#### Link to the extension https://commercemarketplace.adobe.com/cybersource-global-payment-management.html
#### Tested on 3.5.6

- Call transaction API on failure: yes
- Payment data available:
  - Bin: no
  - Last4: no
  - Expiry date: yes
  - Cardholder name: yes

### Cybersource (Checkout API)
#### Link to the extension https://commercemarketplace.adobe.com/cybersource-global-payment-management.html
#### Tested on 3.5.6

- Call transaction API on failure: yes
- Payment data available:
  - Bin: no
  - Last4: no
  - Expiry date: no
  - Cardholder name: no

### Amazon Pay / PayPal Express
#### Magento built in
#### Tested on 101.0.6

Not compatible with any pre auth flows, not even the basic behavior to block the customer on pre auth decline. Needs custom work on checkout as it has a specific behavior on checkout process.
