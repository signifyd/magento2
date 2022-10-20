# Add pagination to cron processing

To define the maximum number of cases that will be processed by cron each time, it is necessary to add the quantity to the configuration, the value must be an integer.
By default, extension will process all cases that were not processed at once.

### Setting cron pagination.

To set cron pagination run command below on your database:

```
INSERT INTO core_config_data (path, value) VALUES ('signifyd/advanced/cron_pagination', INTEGER);
```
### Update cron pagination

To modify cron pagination, insert the new quantity you want to and then run the following command on your database:

```
UPDATE core_config_data SET value=INTEGER WHERE path='signifyd/advanced/cron_pagination';
```
### Delete cron pagination

To revert to the extension's default cron pagination, just delete it from the database:

```
DELETE FROM core_config_data WHERE path='signifyd/advanced/cron_pagination';
```

### Check current cron pagination

To check the current cron pagination, run the command below on your database:

```
SELECT * FROM core_config_data WHERE path = 'signifyd/advanced/cron_pagination';
```