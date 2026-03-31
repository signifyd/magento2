# Signifyd Extension for Magento 2

[![Latest Stable Version](https://img.shields.io/github/v/release/signifyd/magento2)](https://github.com/signifyd/magento2/releases)
[![License](https://img.shields.io/github/license/signifyd/magento2)](https://github.com/signifyd/magento2/blob/master/LICENSE)

Signifyd's Magento 2 extension enables merchants to integrate with the **Signifyd V3 API**, automating fraud prevention and protecting against chargebacks with Guaranteed Fraud Protection.

For questions or issues, please [contact our support team](https://community.signifyd.com/support/s/).  
For full API reference, see [developer.signifyd.com](https://developer.signifyd.com).

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
    - [Install / Update via Composer](#install--update-via-composer)
    - [Uninstall Extension](#uninstall-extension)
- [Configuration](#configuration)
- [Logs](#logs)
- [Advanced Settings](#advanced-settings)
    - [Restrict Orders by States](#restrict-orders-by-states)
    - [Restrict Orders by Payment Methods](#restrict-orders-by-payment-methods)
    - [Add Carrier / Method Mappings](#add-carrier--method-mappings)
    - [Add Payment Method Mappings](#add-payment-method-mappings)
    - [Pass Custom Payment Data via Gateway APIs](#pass-custom-payment-data-via-gateway-apis)
    - [Pass Custom Payment Data via Payment Mappers](#pass-custom-payment-data-via-payment-mappers)
    - [Pass Payment Details – Pre-Auth Policy](#pass-payment-details--pre-auth-policy)
- [Support](#support)

---

## Requirements

- Magento 2.4 or higher
- PHP 7.4 or higher
- [Composer](https://getcomposer.org/) installed on your environment
- A Signifyd account and API key ([find yours here](https://app.signifyd.com/settings))

---

## Installation

### Install / Update via Composer

Composer is the recommended way to install and manage the Signifyd extension. It handles all dependency resolution automatically.

> **Note:** Depending on your OS and how Composer is installed, you may need to replace `composer` with `composer.phar` in the commands below.

Run the following commands in your terminal to install or update the extension to the [latest release](https://github.com/signifyd/magento2/releases):

```bash
cd MAGENTO_ROOT
composer config repositories.signifydmage2 git https://github.com/signifyd/magento2.git
composer require signifyd/module-connect
bin/magento setup:upgrade
bin/magento setup:di:compile
```

---

### Uninstall Extension

> **Note:** The following steps only apply if the extension was installed via Composer.

To completely remove the extension, run this command in your terminal:

```bash
cd MAGENTO_ROOT
composer remove signifyd/module-connect
bin/magento setup:upgrade
bin/magento setup:di:compile
```

Then run the following query on your MySQL database to clean up the module registration:

```sql
DELETE FROM setup_module WHERE module='Signifyd_Connect';
```

To purge all Signifyd extension data from your Magento instance, follow the [install troubleshooting guide](docs/INSTALL-TROUBLESHOOT.md#purge-all-signifyd-data).

---

## Configuration

After installation, refer to the Magento 2 product manual to learn how to [configure the extension](https://community.signifyd.com/support/s/article/magento-2-extension-install-guide), including how to enter your API key and select your integration flow (Pre-Auth or Post-Auth).

---

## Logs

The extension writes logs to the following files under your Magento root:

| Log Level | File Path |
|-----------|-----------|
| Info / General | `MAGENTO_ROOT/var/log/signifyd_connect.log` |
| Debug (when debug mode is enabled) | `MAGENTO_ROOT/var/log/signifyd_connect_debug.log` |

---

## Advanced Settings

These settings provide fine-grained control over advanced extension capabilities.

> **Important:** Changes to these settings should only be made by an experienced developer under the supervision of the Signifyd support team. Incorrect configuration may cause integration issues.

---

### Restrict Orders by States

Restrict orders with specific order **states** (not statuses) from being sent to Signifyd.

See: [Restrict orders by states](docs/RESTRICT-STATES.md)

---

### Restrict Orders by Payment Methods

Restrict orders with specific payment methods from being sent to Signifyd.

See: [Restrict orders by payment methods](docs/RESTRICT-PAYMENTS.md)

---

### Add Carrier / Method Mappings

Map custom shipping carriers and methods from Magento to Signifyd's expected format.

See: [Carrier / method mapping](docs/SHIPPING-MAPPING.md)

---

### Add Payment Method Mappings

Map custom payment methods from Magento to Signifyd's expected format.

See: [Payment method mapping](docs/PAYMENT-MAPPING.md)

---

### Pass Custom Payment Data via Gateway APIs

The extension can use external classes to collect payment data (`avsResponseCode`, `cvvResponseCode`, `cardBin`, `cardLast4`, `cardExpiryMonth`, `cardExpiryYear`) directly from payment gateway APIs when submitting an order for guarantee.

If these fields are missing from submitted orders, you can pass them using existing gateway integrations available in the SDK or by building your own custom gateway class.

See: [Payment gateways](docs/PAYMENT-DETAILS-GATEWAY.md)

---

### Pass Custom Payment Data via Payment Mappers

The extension will also attempt to collect the same payment data fields from Magento's native data layer. If any fields are missing, you can supply them through the extension's built-in mapper system.

See: [Payment mappers](docs/PAYMENT-DETAILS.md)

---

### Pass Payment Details – Pre-Auth Policy

In the Pre-Auth flow, the Signifyd case is created before the payment is submitted to the payment gateway. This means `avsResponseCode`, `cvvResponseCode`, and `transactionId` are not yet available. However, it is still possible to collect `cardBin`, `cardLast4`, `cardExpiryMonth`, and `cardExpiryYear` using a JavaScript snippet at checkout time.

See: [Pass payment details – Pre-Auth](docs/PASS-PAYMENT-PRE-AUTH.md)

---

## Support

- **Documentation:** [developer.signifyd.com](https://developer.signifyd.com)
- **API Reference (V3):** [developer.signifyd.com/main/reference](https://developer.signifyd.com/main/reference)
- **Magento 2 Extension Guide:** [community.signifyd.com](https://community.signifyd.com/support/s/article/magento-2-extension-install-guide)
- **Community & Support:** [community.signifyd.com](https://community.signifyd.com/support/s/)
- **Bug Reports / Feature Requests:** [Open an issue](https://github.com/signifyd/magento2/issues)

---

## License

MIT License. See [LICENSE](LICENSE) for details.