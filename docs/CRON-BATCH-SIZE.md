# Change cron batch size

It's possible to change the number of cases which will be process on each cron run.
The default value is 20 cases per run.

### Setting cron batch size.

To set the cron batch size, run this command on database:

```
INSERT INTO core_config_data (path, value) VALUES ('signifyd/advanced/cron_batch_size', BATCH_SIZE);
```
### Update cron batch size

To modify the cron batch size, replace BATCH_SIZE with the desired value and run this command on database:

```
UPDATE core_config_data SET value=BATCH_SIZE WHERE path='signifyd/advanced/cron_batch_size';
```
### Delete cron batch size

To revert to the cron batch size to extension's default, delete the setting from database:

```
DELETE FROM core_config_data WHERE path='signifyd/advanced/cron_batch_size';
```

### Check current cron batch size

To check the current cron batch size, run this command on your database:

```
SELECT * FROM core_config_data WHERE path = 'signifyd/advanced/cron_batch_size';
```

If no result it's returned, then extension will use the default setting, 20.