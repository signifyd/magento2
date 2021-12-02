[Signifyd Extension for Magento 2](../README.md) > Payment Method Mapping

# Payment method mapping

By default Signifyd extension maps Magento built in payment methods, but it is possible to add other.

### Add custom payment method mapping

To add new pyament methods, it is necessary to add all existing mappings with those to be added.

Payment method code is defined by payment extension developer and usually can be found on etc/config.xml file or on payment method model file at _code property.

#### Custom payment method mapping

Add the desired payment method Signifyd and Magento codes to the JSON string on command below and run it on your databse:

```sql
INSERT INTO core_config_data (path, value) VALUES ('signifyd/general/payment_methods_config', '{"CREDIT_CARD":["payflow_link", "payflow_advanced", "authorizenet_acceptjs", "adyen_cc", "braintree", "cybersource", "stripe_payments", "anet_creditcard", "authorizenet_directpost", "openpay_cards"],"CHECK":["checkmo"], "SIGNIFYD-PAYMENT-CODE": ["magento-payment-code"]}');
```

A list of the possible values for SIGNIFYD-PAYMENT-CODE can be founded on Signifyd API docs, look for transactions.paymentMethod field.

[https://developer.signifyd.com/api/#/reference/cases/create-case](https://developer.signifyd.com/api/#/reference/cases/create-case)

### Check current payment method mapping

To check the current payment method mapping, run the command below on your database:

```sql
SELECT * FROM core_config_data WHERE path='signifyd/general/payment_methods_config';
```

If no records are found look for extensions default mappings on etc/config.xml file under extension folder.
