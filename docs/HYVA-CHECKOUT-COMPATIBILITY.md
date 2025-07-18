# Hyvä Checkout Compatibility

## Overview
When we adopt Hyvä Checkout, we fundamentally replace Magento’s default (Luma) checkout structure with a modern, tailored UI built on Magewire, Alpine.js, and Tailwind CSS.

Because of this, any custom functionality or third-party extension designed for the default checkout must be adapted to operate correctly within the new architecture.

If you plan to use Hyvä Checkout, make sure to apply the compatibility patches we’ve provided to ensure proper integration with the module.

## Compatible methods

### Braintree on Hyvä Checkout
#### Magento built in + hyva-themes/magento2-hyva-checkout-braintree Hyvä Compatibility module
#### Tested on Braintree module 4.6.1-p5
#### Braintree Hyvä compatibility module 1.1.0

## Steps to apply the compatibility patch

To apply the compatibility patches, follow these steps:

1. Copy the patch to the root directory of your Magento installation
```
cd [MAGENTO_ROOT]
cp vendor/signifyd/module-connect/Patch/pre-auth-braintree-hyva-checkout.patch .
```

2. Apply the path
```
git apply pre-auth-braintree-hyva-checkout.patch
```

