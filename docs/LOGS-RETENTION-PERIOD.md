[Signifyd Extension for Magento 2](../README.md) > Logs retention period

# Logs retention period

By default, Signifyd extension stores logs for a period of 60 days. Logs can be downloaded from the order view.

### Add custom period

The custom value must be an integer representing the number of days.
To include custom period use the command below on your database:

```
INSERT INTO core_config_data (path, value) VALUES ('signifyd/advanced/logs_retention_period', 70);
```

### Update custom period

To modify an existing custom period, use the command below:

```
UPDATE core_config_data SET value=70 WHERE path='signifyd/advanced/logs_retention_period';
```

### Delete custom period

To use the extension default period, use the command below:

```
DELETE FROM core_config_data WHERE path='signifyd/advanced/logs_retention_period';
```

### Checking current custom period

To check the current custom period, run the command below:

```
SELECT * FROM core_config_data WHERE path='signifyd/advanced/logs_retention_period';
```
