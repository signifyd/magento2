# Signifyd Extension for Magento 2

Signifyd’s Magento extension enables merchants on Magento 2 to integrate with Signifyd, automating fraud prevention and protecting them in case of chargebacks.

## Install/update using composer

Composer is a tool for dependency management in PHP. It allows you to declare the libraries your project depends on and it will manage (install/update) them for you.

You can learn more about it at [https://getcomposer.org](https://getcomposer.org).

Before getting started make sure you have composer properly installed on your environment.

*Note: depending on the operating system and how composer is installed, you may need to add '.phar' after 'composer' using the command lines, i.e. change from 'composer' => 'composer.phar'*

### Install/update

With composer installed, run the command below on terminal. This will install/update Signifyd extension to the [latest release](https://github.com/signifyd/magento2/releases).

```bash
cd MAGENTO_ROOT
composer config repositories.signifydmage2 git https://github.com/signifyd/magento2.git
composer require signifyd/module-connect
bin/magento setup:upgrade
bin/magento setup:di:compile
``` 

### Uninstall extension

**This only applies if extension has been installed using composer** 

To remove extension completely, run this command in terminal.

```bash
cd MAGENTO_ROOT
composer remove signifyd/module-connect
bin/magento setup:upgrade
bin/magento setup:di:compile
```

And run this command on MySQL.

```mysql
DELETE FROM setup_module WHERE module='Signifyd_Connect';
```

You can also delete all Signifyd extension data using this guide, [install troubleshooting doc](docs/INSTALL-TROUBLESHOOT.md#purge-all-signifyd-data).

## Configure
View our Magento 2 product manual to learn how to [configure the extension](https://community.signifyd.com/support/s/article/magento-2-extension-install-guide)

## Logs

Info or general logs can be found on MAGENTO_ROOT/var/log/signifyd_connect.log file.
If debug mode is enabled those logs can be found on MAGENTO_ROOT/var/log/signifyd_connect_debug.log

## Advanced Settings

These settings enable fine grain control over advanced capabilities of the extension.

_Updating these settings should only be performed by an experienced developer under the supervision of the Signifyd support team. If these steps are not completed correctly an issue may occur._

### Restrict orders by states

Restrict orders with specific order states (not status) from being sent to Signifyd.

[Restrict orders by states](docs/RESTRICT-STATES.md) 

### Restrict orders by payment methods

Restrict orders with specific payment methods from being sent to Signifyd.

[Restrict orders by payment methods](docs/RESTRICT-PAYMENTS.md)

### Add carriers/methods mappings

Map custom shipping carriers and methods from Magento to Signifyd.

[Carrier/method mapping](docs/SHIPPING-MAPPING.md)

### Add payment methods mappings

Map custom payment methods from Magento to Signifyd.

[Payment method mapping](docs/PAYMENT-MAPPING.md)

### Pass custom payment data using payment gateways APIs

The Signifyd extension will use external class to collect payment data (avsResponseCode, cvvResponseCode, cardBin, cardLast4, cardExpiryMonth and cardExpiryYear) from payment gateway APIs when submitting an order for guarantee. If these fields are missing from submitted orders you can pass these fields by using existing gateways APIs integrations on our SDK or building your own. 

[Payment gateways](docs/PAYMENT-DETAILS-GATEWAY.md)

### Pass custom payment data using payment mappers

The Signifyd extension will try to collect payment data (avsResponseCode, cvvResponseCode, cardBin, cardLast4, cardExpiryMonth and cardExpiryYear) from Magento when submitting an order for guarantee. If these fields are missing from submitted orders you can pass these fields by using the extension's mappers. 

[Payment mappers](docs/PAYMENT-DETAILS.md)

### Pass payment details - pre auth policy

On the pre auth policy, Signifyd cases are created before the payment being submitted to the payment gateway. So, AVS code, CVV code and transaction ID are not available at all at this moment. But it is desirable to try to collect most of these information: bin, last4, expiry month and expiry year.
In order to do that, it's needed to use some JavaScript code to collect most information as possible.

[Pass payment details](docs/PASS-PAYMENT-PRE-AUTH.md)

## Patch Compatibility with Different Versions
We've tested this patch for a specific version, and it may not work on lower versions. If you're using an older version, additional adjustments might be necessary to ensure compatibility.

## Applying Patches to Higher Versions
Typically, applying a patch to a higher version is not necessary since we include all fixes in the next release. Therefore, it is recommended to upgrade the module rather than apply patches manually to newer versions.

## Applying Patches in Adobe Commerce
For Adobe Commerce, the official method to apply patches is documented by Adobe. You can find detailed instructions at:
[Adobe Commerce Patch Application Guide](https://experienceleague.adobe.com/en/docs/commerce-on-cloud/user-guide/develop/upgrade/apply-patches)

## Applying Patches in Magento Open Source (Community Edition)
There is no official method for applying patches in Magento Open Source. Each developer may implement their own approach.

## How to Apply Patches
To manually apply a patch in Magento, follow these steps:

1. Copy the patch to the root directory of your Magento installation
```
cd [MAGENTO_ROOT]
cp vendor/signifyd/module-connect/Patch/<patch_file_name>.patch .
```

2. Apply the path
```
git apply <patch_file_name>.patch
```

## 5.3.0
### MAG-930 Merchant's orders remaining on hold
Description: Some of the orders are updated in signified with guarantee approved but when Magento requests  for the change in the Magento system, we get the same hash code.
File: [5.3.0-MAG-930-orders-remaining-on-hold.patch](./5.3.0-MAG-930-orders-remaining-on-hold.patch)

## 5.6.0
### MAG-929 Adobe Commerce 2.4.7-p1 - Issues with Signifyd Extension
Description: There are several changes to be made to correct inline Javascript. Adobe has blocked all inline script/styles loading and also elements from external domains.
File: [5.6.0-MAG-928-transaction-api.patch](./5.6.0-MAG-928-transaction-api.patch)

## 5.7.0
### MAG-908 Create mappers to map paymentMethod options
Description: Some Magento payment methods join multiple options in a single code which is not mappable on the current design..
File: [5.7.0-MAG-908-adyen-pay-by-link-mapper-fixed.patch](./5.7.0-MAG-908-adyen-pay-by-link-mapper-fixed.patch)

### MAG-912 Case lock halting cron execution
Description: When a case is locked by another process and cron tries to process it, the whole cron job is being halted.
File: [5.7.0-MAG-912-case-lock-halting-cron-execution.patch](./5.7.0-MAG-912-case-lock-halting-cron-execution.patch)

## 5.8.0
### MAG-929 Adobe Commerce 2.4.7-p1 - Issues with Signifyd Extension
Description: There are several changes to be made to correct inline Javascript. Adobe has blocked all inline script/styles loading and also elements from external domains.
File: [5.8.0-MAG-929-csp-issue-with-signifyd-extension.patch](./5.8.0-MAG-929-csp-issue-with-signifyd-extension.patch)

## 5.8.1
### MAG-914 Magento 2.4.7: Fix parent constructor call in Abandoned Grid
Description: Error during setup di compile command.
File: [5.8.1-MAG-914-magento-2.4.7-fix-parent-constructor-call-in.patch](./5.8.1-MAG-914-magento-2.4.7-fix-parent-constructor-call-in.patch)

### MAG-919 Orders stuck "On Hold" after Signifyd review
Description: A select few orders are being reviewed by Signifyd, then left “on hold” without being updated back to “In processing”.
File: [5.8.1-MAG-919-orders-stuck-on-hold-after-signifyd-review.patch](./5.8.1-MAG-919-orders-stuck-on-hold-after-signifyd-review.patch)

### MAG-928 No Transaction API update for orders that failed auth
Description: Not receiving the Transaction API update for orders that failed Adyen authorization.
File: [5.8.1-MAG-928-no-transaction-api.patch](./5.8.1-MAG-928-no-transaction-api.patch)

### MAG-931 Change thumbs up/down behavior
Description: When click on the thumbs up/down on Signifyd that triggers a webhook call to Signifyd. We want to do not process such call unless a hidden configuration is enabled.
File: [5.8.1-MAG-931-change-thumbs-up-down-behavior.patch](./5.8.1-MAG-931-change-thumbs-up-down-behavior.patch)

### MAG-932 Protect personal data on Magento logs
Description: Creation of a privacy filter to prevent storing customers' personal information in the database.
File: [5.8.1-MAG-932-protect-personal-data-on-magento-logs.patch](./5.8.1-MAG-932-protect-personal-data-on-magento-logs.patch)
SDK file: [5.8.1-MAG-932-sdk-protect-personal-data-on-magento-logs.patch](./5.8.1-MAG-932-sdk-protect-personal-data-on-magento-logs.patch)

### MAG-933 Accept.js data error on Checkout Page
Description: Accept.js data error on checkout page when the Magento plugin is installed. When removed, the error is no longer there.
File: [5.8.1-MAG-933-accept.js-data-error-on-checkout-page.patch](./5.8.1-MAG-933-accept.js-data-error-on-checkout-page.patch)

### MAG-937 Issue with Fulfillment Method mapping
Description: The fulfillment field is not being sent correctly.
File: [5.8.1-MAG-937-issue-with-fulfillment-method-mapping.patch](./5.8.1-MAG-937-issue-with-fulfillment-method-mapping.patch)

### MAG-939 Review Paradox Labs AuthorizeNet integration
Description: To assert that all the available data is collected from Magento database and sent to Signifyd for the post auth flow.
File: [5.8.1-MAG-939-review-paradox-labs-authorizenet-integration.patch](./5.8.1-MAG-939-review-paradox-labs-authorizenet-integration.patch)

### MAG-940 Review Paradox Labs AuthorizeNet integration
Description: Upon guest orders creations the fingerprint system is sending different session IDs on the store front and API calls.
File: [5.8.1-MAG-940-fingerprint-miss-matching-session-id.patch](./5.8.1-MAG-940-fingerprint-miss-matching-session-id.patch)

### MAG-941 Orders failing to invoice and missing cardBin
Description: The extension was not automatically invoicing and unholding the orders..
File: [5.8.1-MAG-941-set-flag-processed_by_gateway-to-all-methods.patch](./5.8.1-MAG-941-set-flag-processed_by_gateway-to-all-methods.patch)

### MAG-942 Review Adyen integration for 9.4.0 and up
Description: From 9.4.1 and up Adyen removed the version number from the module.xml file. That caused our extension to do not fetch the proper version number.
File: [5.8.1-MAG-942-review-adyen-integration-for-9.4.0-and-up.patch](./5.8.1-MAG-942-review-adyen-integration-for-9.4.0-and-up.patch)

### MAG-943 Device ID no longer being received from Wahoo Fitness since they updated to Signifyd Magento ext. version 5.8.1
Description: After a product is added to the cart, the console shows that the fingerprint has already been sent. However, if you monitor the Network tab and filter by cdn-scripts.signifyd.com, you'll see that it is never actually sent.
File: [5.8.1-MAG-943-device-id-no-longer-being-received.patch](./5.8.1-MAG-943-device-id-no-longer-being-received.patch)

### MAG-946 Fix PHP lib float to int implicit conversion
Description: The implicit conversion of 0.25 from float to int is causing some environments to break the case creation process throwing an error.
File: [5.8.1-MAG-946-float-to-int-conversion.patch](./5.8.1-MAG-946-float-to-int-conversion.patch)

### MAG-950 CSP Compliance
Description: The Signifyd module is not CSP compliant in version 5.8.1, which causes it to break the CSP checkout inline strict mode.
File: [5.8.1-MAG-950-csp-compliance.patch](./5.8.1-MAG-950-csp-compliance.patch)

### MAG-951 Groupe SEB - Order failed to have a case created due to Signifyd outage
Description: The case is not being created in case of a failure when attempting to create it via the API.
File: [5.8.1-MAG-951-order-failed-to-have-a-case-created.patch](./5.8.1-MAG-951-order-failed-to-have-a-case-created.patch)
SDK file: [5.8.1-MAG-951-order-failed-to-have-a-case-created-due-to-sdk.patch](./5.8.1-MAG-951-order-failed-to-have-a-case-created-due-to-sdk.patch)

### MAG-950  Exception error when upgrading to Magento version 2.4.7 (-p4)
Description: The implicit conversion of 0.25 from float to int is causing some environments to break the case creation process throwing an error.
File: [5.8.1-MAG-946-float-to-int-conversion.patch](./5.8.1-MAG-946-float-to-int-conversion.patch)
