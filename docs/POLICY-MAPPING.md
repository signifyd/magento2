# Policy mapping

Signifyd has a mapping for the response type that should be applied to the case: an asynchronous response that will require subscribing to webhook events or polling the Get Case API or a synchronous response.

By default the extension will automatically use asynchronous response (POST_AUTH), but it is possible to set synchronous response (PRE_AUTH).

### Setting synchronous response

To set synchronous response run command below on your databse:

```sql
INSERT INTO core_config_data (path, value) VALUES ('signifyd/advanced/policy_name', 'PRE_AUTH');
```

### Setting asynchronous response

To revert back to the extension's default policy, just delete it from the database:

```sql
DELETE FROM core_config_data WHERE path = 'signifyd/advanced/policy_names';
```

### Check policy

To check the current policy, run the command below on your database:

```sql
SELECT * FROM core_config_data WHERE path = 'signifyd/advanced/policy_namess';
```

If no records are found, the extension will automatically use asynchronous response.
