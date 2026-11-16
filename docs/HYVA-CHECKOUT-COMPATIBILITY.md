# Hyvä Checkout Compatibility

## Overview
When we adopt Hyvä Checkout, we fundamentally replace Magento’s default (Luma) checkout structure with a modern, tailored UI built on Magewire, Alpine.js, and Tailwind CSS.

Because of this, any custom functionality or third-party extension designed for the default checkout must be adapted to operate correctly within the new architecture.

If you plan to use Hyvä Checkout, make sure to apply the compatibility patches we’ve provided to ensure proper integration with the module.

## How it works

On the default checkout the payment data collected by JavaScript is submitted to Magento together with the payment information request, and the extension reads it from that request. Hyvä Checkout does not use that request, so the payment data has to reach the backend through a different path.

The extension adds a script to the checkout payment step which stores the payment data on the quote payment. When the order is placed, the extension reads the data from the quote payment and includes it on the pre auth case creation request.

The data is collected in two ways. Payment methods which expose the card data through a JavaScript SDK are read by intercepting that SDK, so the payment method extension does not need to be modified. Payment methods which do not expose the data send it through the global helper:

```
await window.signifydSetCardData({
    cardBin: '411111',
    cardLast4: '1111',
    cardExpiryMonth: '09',
    cardExpiryYear: '2030',
    holderName: 'J. Smith'
});
```

Only the keys above are accepted, any other data is discarded. The helper can be safely called with only part of the data, the keys with no value are ignored. Data collected for a payment method is not used when the order is placed with another one.

Payment methods which render the card fields on the store page are read from the fields themselves,
through the `autocomplete` attributes of the HTML standard. Only the bin, the last four digits and the
expiry date are kept, the card number is never stored nor sent.

Payment methods which store the payment data on the backend before the order is placed do not need any
JavaScript at all, the extension reads the data directly from the quote payment.

## Steps to apply the compatibility

Hyvä Checkout support ships with the extension. No patch is needed, and no payment method extension has
to be modified. Deploy the extension as usual:

```
bin/magento setup:upgrade
bin/magento cache:flush
```

## Compatible methods

### Adyen on Hyvä Checkout
#### Tested on adyen/module-payment 10.x with hyva-themes/magento2-hyva-checkout-adyen-payment-v2 on Hyvä Checkout 1.3
#### Also implemented for adyen/module-hyva-checkout 1.3.0
#### No patch required

The Adyen compatibility module is not modified. The extension intercepts the Adyen web SDK to read the card
data from the callbacks of the card component, and stores it on the quote payment right before the order is
placed. Both the `AdyenWeb` global of the web SDK 6 and the `AdyenCheckout` global of the web SDK 5 are
supported, so it works with either Adyen compatibility module.

#### New cards (adyen_cc)

The expiry date is encrypted by the Adyen client side encryption and never exposed to the checkout, so it
cannot be collected. The cardholder name is only available when the card holder name field is enabled on
the Adyen payment method configuration.

- Payment data available:
    - Bin: yes
    - Last4: yes
    - Expiry date: no
    - Cardholder name: yes (when the card holder name field is enabled)

#### Stored cards (adyen_cc_vault)

Nothing is collected on the checkout for stored cards. Last4 and expiry date are read from the Magento
vault token. On Adyen 9.x and earlier the bin is fetched from the Adyen recurring contracts of the shopper,
Adyen 10 removed that API.

- Payment data available:
    - Bin: only on Adyen 9.x and earlier, for registered customers
    - Last4: yes
    - Expiry date: yes
    - Cardholder name: no

### Braintree on Hyvä Checkout
#### Bundled with Magento 2.4.x (paypal/module-braintree) + hyva-themes/magento2-hyva-checkout-braintree Hyvä Compatibility module
#### Tested on Braintree module 4.6.1-p5
#### Braintree Hyvä compatibility module 1.1.0
#### No patch required

The Braintree compatibility module is not modified. Braintree renders the card fields on iframes of its
own, so the extension intercepts the Braintree web SDK to read the card data from the payload of the
tokenization, which happens before the order is placed.

- Payment data available:
    - Bin: yes
    - Last4: yes
    - Expiry date: yes
    - Cardholder name: no

### Authorize.net ParadoxLabs on Hyvä Checkout
#### Link to the extension https://github.com/ParadoxLabs-Inc/authnetcim-hyva-checkout
#### Implemented against paradoxlabs/authnetcim-hyva-checkout 3.1.0
#### No patch required

What can be collected depends on the payment form type configured on the payment method. The Hyvä
compatibility module of the payment method supports Accept.js and Accept Hosted only.

#### Accept.js form

The card fields are rendered on the store page, so the bin, the last four digits and the expiry date
are read from the fields. There is no cardholder name field on the form, the billing name is used
instead by the extension.

- Payment data available:
    - Bin: yes
    - Last4: yes
    - Expiry date: yes
    - Cardholder name: no

#### Accept Hosted form, and stored cards on either form

The card is typed inside an Authorize.net iframe, or it is already tokenized, so nothing is available
on the checkout. The payment method tokenizes the card before the order is placed, and the extension
reads the payment data from the stored card assigned to the quote payment. The bin is only stored by
the payment method when its `can_store_bin` setting is enabled.

- Payment data available:
    - Bin: yes (when enabled on the payment method configuration)
    - Last4: yes
    - Expiry date: yes
    - Cardholder name: no

### Stripe on Hyvä Checkout
#### Link to the extension https://commercemarketplace.adobe.com/stripe-stripe-payments.html
#### Implemented against hyva-themes/magento2-hyva-checkout-stripe with stripe/stripe-payments 4.6
#### No patch required

The Stripe compatibility module is not modified. Stripe renders the card fields inside the Payment Element
iframe, so the card data is never available on the store page. The extension intercepts the Stripe SDK to
read the card preview of the token which the checkout creates while it validates the payment, which happens
before the order is placed.

> [!IMPORTANT]
> The card bin is not exposed to the browser by Stripe, so it cannot be collected on the pre auth flow

- Payment data available:
    - Bin: no
    - Last4: yes
    - Expiry date: yes
    - Cardholder name: yes (the billing details name of the payment form)
