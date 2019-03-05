# Signifyd Extension for Magento 2

Signifyd’s Magento extension enables merchants on Magento 2 to integrate with Signifyd, automating fraud prevention and protecting them in case of chargebacks.

## Install/update using composer

Composer is a tool for dependency management in PHP. It allows you to declare the libraries your project depends on and it will manage (install/update) them for you.

It is possible to learn more about it, download and install it on [https://getcomposer.org](https://getcomposer.org).

Before getting started make sure to have composer properly installed on environment.

*Note: depending on operational system and how composer is installed, may be needed to add '.phar' after 'composer' on command lines, changing from 'composer' => 'composer.phar'*

### Install/update

With composer installed on environment, run below command on terminal to install/update Signifyd extension to latest release.

```bash
cd MAGENTO_ROOT
composer require signifyd/module-connect
bin/magento setup:upgrade
bin/magento setup:di:compile
``` 

### Uninstall extension

**Only use these commands if extension has been installed using composer** 

To remove extension completely, run below commands on terminal.

```bash
cd MAGENTO_ROOT
composer remove signifyd/module-connect
bin/magento setup:upgrade
bin/magento setup:di:compile
```

And run below command on MySQL.

```mysql
DELETE FROM setup_module WHERE module='Signifyd_Connect';
```

If it is desirable to purge all extension data view the [install troubleshooting doc](docs/INSTALL-TROUBLESHOOT.md#purge-all-signifyd-data).

## Configure
View our Magento 2 product manual to learn how to [configure the extension](https://www.signifyd.com/resources/manual/magento-v2-1/)

## Logs

Logs can be found on MAGENTO_ROOT/var/log/signifyd_connect.log file.

## Advanced Settings

These settings enable fine grain control over advanced capabilities of the extension.

_Updating these settings should only be performed by an experienced developer under the supervision of the Signifyd support team. If these steps are not completed correctly they may cause issues._

### Pass custom payment data using payment helpers

The Signifyd extension will try to collect payment data (avsResponseCode, cvvResponseCode, cardBin, cardLast4, cardExpiryMonth and cardExpiryYear) from Magento when submitting an order for guarantee. If these fields are missing from submitted orders you can pass these fields by using the extension's mappers. 

[Payment mappers](docs/PAYMENT-DETAILS.md)
