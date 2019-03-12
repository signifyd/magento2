# Signifyd Extension for Magento 2

Signifyd’s Magento extension enables merchants on Magento 2 to integrate with Signifyd, automating fraud prevention and protecting them in case of chargebacks.

## Install/update using composer

Composer is a tool for dependency management in PHP. It allows you to declare the libraries your project depends on and it will manage (install/update) them for you.

You can learn more about it at [https://getcomposer.org](https://getcomposer.org).

Before getting started make sure you have composer properly installed on your environment.

*Note: depending on the operating system and how composer is installed, you may need to add '.phar' after 'composer' using the command lines, i.e. change from 'composer' => 'composer.phar'*

### Install/update

With composer installed, run the command below on terminal. This will install/update Signifyd extension to [latest release](https://github.com/signifyd/magento2/releases).

```bash
cd MAGENTO_ROOT
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
View our Magento 2 product manual to learn how to [configure the extension](https://www.signifyd.com/resources/manual/magento-v2-1/)

## Logs

Logs can be found on MAGENTO_ROOT/var/log/signifyd_connect.log file.

## Advanced Settings

These settings enable fine grain control over advanced capabilities of the extension.

_Updating these settings should only be performed by an experienced developer under the supervision of the Signifyd support team. If these steps are not completed correctly an issue may occur._

### Restrict orders by states

Restrict orders with specific order states (not status) from being sent to Signifyd.

[Restrict orders by states](docs/RESTRICT-STATES.md) 

### Restrict orders by payment methods

Restrict orders with specific payment methods from being sent to Signifyd.

[Restrict orders by payment methods](docs/RESTRICT-PAYMENTS.md) 

### Pass custom payment data using payment helpers

The Signifyd extension will try to collect payment data (avsResponseCode, cvvResponseCode, cardBin, cardLast4, cardExpiryMonth and cardExpiryYear) from Magento when submitting an order for guarantee. If these fields are missing from submitted orders you can pass these fields by using the extension's mappers. 

[Payment mappers](docs/PAYMENT-DETAILS.md)
