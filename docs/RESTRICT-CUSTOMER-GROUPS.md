[Signifyd Extension for Magento 2](../README.md) > Restrict orders by customer groups

# Restrict orders by customer groups

**_Warning: These steps should only be performed under the instruction and supervision of Signifyd. If these steps are not completed correctly you may experience issues with the Signifyd extension and or your Magento store. It is recommended to test this on a development environment first._**

## Things to know before getting started

Orders placed by a group of customers can be excluded from being sent to Signifyd. These orders will not be created in Signifyd and the extension will not interfere with the order workflow i.e. place the order on hold or capture the payment.

If you want to exclude customer groups you will need to provide a list of customer groups id in a comma separated list `2,5,7`. You will also need to clear the configuration or full cache for the change to take effect.

### Add custom customer groups

Insert the list of customer groups id you want to restrict and then run the command below on your database:

```
`INSERT INTO core_config_data (path, value) VALUES ('signifyd/general/restrict_customer_groups', 'INSERT-LIST-OF-CUSTOMER-GROUPS-ID-HERE');`
```
### Update custom customer groups

To modify an existing restricted list, insert the list of new customer groups id you want to restrict and then run the following command on your database:

```
UPDATE core_config_data SET value='INSERT-LIST-OF-CUSTOMER-GROUPS-ID-HERE' WHERE path='signifyd/general/restrict_customer_groups';
```
### Delete custom customer groups

To remove all customer group restriction, just delete it from the database:

```
DELETE FROM core_config_data WHERE path='signifyd/general/restrict_customer_groups';
```

### Check current restriction settings

To check the current restricted customer group id, run the command below on your database:

```
SELECT * FROM core_config_data WHERE path = 'signifyd/general/restrict_customer_groups';
