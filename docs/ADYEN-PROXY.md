# Adyen Proxy

### Setting Adyen Proxy

To set Adyen Proxy run command below on your database:

```sql
INSERT INTO core_config_data (path, value) VALUES ('signifyd/general/enabled', 1);
```

### Remove Adyen Proxy

To revert back, just delete it from the database:

```sql
DELETE FROM core_config_data WHERE path = 'signifyd/general/enabled';
```

### Check Adyen Proxy

To check the current policy, run the command below on your database:

```sql
SELECT * FROM core_config_data WHERE path = 'signifyd/general/enabled';
```

If no records are found, the extension will not use Adyen Proxy.
