# Fulfillment method mapping

Signifyd has a mapping for fulfillment method for the shipment.

A list of the possible values for SIGNIFYD-FULFILLMENT can be founded on Signifyd API docs, look for sale.purchase.shipments.fulfillmentMethod field.

[https://docs.signifyd.com/#operation/Sale](https://docs.signifyd.com/#operation/Sale)

### Setting global fulfillment method

If the setting has a string as mentioned in the documentation above then the extension will assume this as the only fulfillment method for all cases.

To set global fulfillment method run command below on your database:

```sql
INSERT INTO core_config_data (path, value) VALUES ('signifyd/advanced/fulfillment_method', 'SIGNIFYD-FULFILLMENT');
```

### Setting fulfillment method per delivery method

It is possible to select a different fulfillment method per delivery method.

If the setting stores a JSON, then it will map each delivery method listed on JSON to the corresponding fulfillment method. Any delivery methods not mapped will fallback to the 'DELIVERY' fulfillment method. Here it is an example of how the final JSON could look like:

```
{"DELIVERY": ["freeshipping_freeshipping"], "CURBSIDE_PICKUP": ["dhl_pickup"]}
```
To set fulfillment method per payment method run command below on your database:

```sql
INSERT INTO core_config_data (path, value) VALUES ('signifyd/advanced/fulfillment_method', 'INSERT-JSON-MAPPING');
```

### Check fulfillment method

To check the current fulfillment method, run the command below on your database:

```sql
SELECT * FROM core_config_data WHERE path = 'signifyd/advanced/fulfillment_method';
```

If no records are found, the extension will automatically use 'DELIVERY' as fulfillment method.
