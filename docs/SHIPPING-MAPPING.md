[Signifyd Extension for Magento 2](../README.md) > Shipping Mapping

# Shipping carrier/method mapping

Signifyd has a mapping for the following carriers FEDEX, DHL, USPS, UPS and their delivery methods.

### Add custom carrier/method mapping

To add new carriers and methods, it is necessary to add all existing mappings with those to be added.

Carrier code it is an unique string which identifies a carrier on Magento. It is defined by carrier extension developer and usually can be found on config.xml or carrier model file into the extension code.

Similar to carrier code the method code is defined by carrier extension developer and usually can be found on getAvaliableMethods method on carrier model file.

To find out a carrier and method code for a specific order look into sales_order.shipping_method on database. Both codes are separeted by an underscore. The first part it is the carrier code and the remaning it is part it is the method code.

#### Custom carrier mapping

Add the desired carrier Signifyd and Magento codes to the JSON string on command below and run it on your databse:

```sql
INSERT INTO core_config_data (path, value) VALUES ('signifyd/general/shipper_config', '{"FEDEX":["fedex"],"DHL":["dhl"],"SHIPWIRE":[],"USPS":["usps"],"UPS":["ups"],"SIGNIFYD-CARRIER-CODE":["magento-carrier-code"]}');
```

A list of the possible values for SIGNIFYD-CARRIER-CODE can be founded on Signifyd API docs, look for purchase.shippiments.shipper field.

[https://developer.signifyd.com/api/#/reference/cases/create-case](https://developer.signifyd.com/api/#/reference/cases/create-case)

### Check current carrier mapping

To check the current carrier mapping, run the command below on your database:

```sql
SELECT * FROM core_config_data WHERE path='signifyd/general/shipper_config';
```
