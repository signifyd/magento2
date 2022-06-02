# Policy mapping

Signifyd has a mapping for the response type that should be applied to the case: an asynchronous response that will require subscribing to webhook events or polling the Get Case API or a synchronous response.

By default the extension will automatically use asynchronous response (POST_AUTH), but it is possible to set synchronous response (PRE_AUTH and TRA_PRE_AUTH).

For more information about transaction risk analysis pre-auth (TRA_PRE_AUTH), access the [documentation](TRA_PRE_AUTH.md).

### Setting global synchronous response

If the setting has a string as "PRE_AUTH" then the extension will assume this as the only policy for all cases.

To set global synchronous response run command below on your database:

```sql
INSERT INTO core_config_data (path, value) VALUES ('signifyd/advanced/policy_name', 'PRE_AUTH');
```

### Setting global asynchronous response

To revert back to the extension's default policy, just delete it from the database:

```sql
DELETE FROM core_config_data WHERE path = 'signifyd/advanced/policy_name';
```

### Setting policy per payment method

It is possible to select a different policy per payment method.

If the setting stores a JSON, then it will map each payment method listed on JSON to the corresponding policy. Any payment methods not mapped will fallback to the POST_AUTH policy. Here it is an example of how the final JSON could look like:

```
{"PRE_AUTH": ["paypal_braintree"], "POST_AUTH": ["checkmo"], "TRA_PRE_AUTH": ["braintree"]}
```
To set policy per payment method run command below on your database:

```sql
INSERT INTO core_config_data (path, value) VALUES ('signifyd/advanced/policy_name', 'INSERT-JSON-MAPPING');
```

### Check policy

To check the current policy, run the command below on your database:

```sql
SELECT * FROM core_config_data WHERE path = 'signifyd/advanced/policy_name';
```

If no records are found, the extension will automatically use asynchronous response.

## Policy decline message

By default, whenever the synchronous policy is configured, if the Signifyd response is negative, the extension will display the following error message on checkout:
```
Your order cannot be processed, please contact our support team
```

### Setting custom decline message

To set custom message run command below on your database:

```sql
INSERT INTO core_config_data (path, value) VALUES ('signifyd/advanced/policy_pre_auth_reject_message', 'CUSTOM-MESSAGE');
```

### Setting default decline message

To revert back to the extension's default message, just delete it from the database:

```sql
DELETE FROM core_config_data WHERE path = 'signifyd/advanced/policy_pre_auth_reject_message';
```

### Check decline message

To check the current message, run the command below on your database:

```sql
SELECT * FROM core_config_data WHERE path = 'signifyd/advanced/policy_pre_auth_reject_message';
```

If no records are found, the extension will automatically use default message.
