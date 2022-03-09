# Adyen Proxy

The Adyen Proxy can be configured either in global (default) scope, store view (stores) or website (websites).

### Setting by website
To find the website id just use the following command in the database:
```sql
select * from store_website;
```
With the id found (for exemple: 2) run command below on your database:

```sql
INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('websites', 2, 'signifyd/proxy/adyen_enable', 1);
```

### Setting by store view
To find the store view id just use the following command in the database:
```sql
select * from store;
```
With the id found (for exemple: 4) run command below on your database:

```sql
INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('stores', 4, 'signifyd/proxy/adyen_enable', 1);
```

### Setting Global
To set global configuration run command below on your database:

```sql
INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('default', 0, 'signifyd/proxy/adyen_enable', 1);
```

### Remove Adyen Proxy

To revert back, just delete it from the database:

```sql
DELETE FROM core_config_data WHERE path = 'signifyd/proxy/adyen_enable';
```

### Check Adyen Proxy

To check the current policy, run the command below on your database:

```sql
SELECT * FROM core_config_data WHERE path = 'signifyd/proxy/adyen_enable';
```

If no records are found, the extension will not use Adyen Proxy.
