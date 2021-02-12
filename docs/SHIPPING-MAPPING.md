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

#### Custom method mapping

Add the desired carrier method Signifyd and Magento codes to the JSON string on command below and run it on your databse:

```sql
INSERT INTO core_config_data (path, value) VALUES ('signifyd/general/shipping_method_config', '{"EXPRESS":["FEDEX_EXPRESS_SAVER", "7", "B", "C", "D", "U", "K", "L", "I", "N", "T", "X", "INT_4", "INT_5", "INT_6", "INT_7", "54", "07"],"ELECTRONIC":[],"FIRST_CLASS":["0_FCLE", "0_FCL", "0_FCP", "0_FCPC", "15", "53", "61", "INT_13", "INT_14", "INT_15", "INT_21"],"FIRST_CLASS_INTERNATIONAL":[],"FREE":["freeshipping"],"FREIGHT":["FEDEX_1_DAY_FREIGHT", "FEDEX_2_DAY_FREIGHT", "FEDEX_3_DAY_FREIGHT", "INTERNATIONAL_ECONOMY_FREIGHT", "INTERNATIONAL_PRIORITY_FREIGHT", "FEDEX_FREIGHT", "FEDEX_NATIONAL_FREIGHT"],"GROUND":["FEDEX_GROUND", "GROUND_HOME_DELIVERY", "INTERNATIONAL_GROUND", "4", "03"],"INTERNATIONAL":["INTERNATIONAL_ECONOMY", "INTERNATIONAL_FIRST"],"OVERNIGHT":["FIRST_OVERNIGHT", "PRIORITY_OVERNIGHT", "STANDARD_OVERNIGHT"],"PRIORITY":["1", "2", "3", "13", "16", "17", "22", "23", "25", "27", "28", "29", "30", "31", "32", "33", "34", "35", "36", "37", "38", "39", "40", "41", "42", "43", "44", "45", "46", "47", "48", "49", "50", "57", "58", "59", "62", "63", "64", "INT_1", "INT_2", "INT_8", "INT_9", "INT_10", "INT_11", "INT_16", "INT_17", "INT_18", "INT_19", "INT_20", "INT_22", "INT_23", "INT_24", "INT_25", "INT_27"],"PRIORITY_INTERNATIONAL":["EUROPE_FIRST_INTERNATIONAL_PRIORITY", "INTERNATIONAL_PRIORITY"],"PICKUP":["pickup"],"STANDARD":["11"],"STORE_TO_STORE":[],"TWO_DAY":["FEDEX_2_DAY", "FEDEX_2_DAY_AM", "59", "02"]}');
```

A list of the possible values for SIGNIFYD-CARRIER-CODE can be founded on Signifyd API docs, look for purchase.shippiments.shippingMethod field.

[https://developer.signifyd.com/api/#/reference/cases/create-case](https://developer.signifyd.com/api/#/reference/cases/create-case)

### Check current carrier mapping

To check the current carrier mapping, run the command below on your database:

```sql
SELECT * FROM core_config_data WHERE path='signifyd/general/shipper_config';
```

### Check current method mapping

To check the current method mapping, run the command below on your database:

```sql
SELECT * FROM core_config_data WHERE path='signifyd/general/shipping_method_config';
```

If no records are found look for extensions default mappings on etc/config.xml file under extension folder.
