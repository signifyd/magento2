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
