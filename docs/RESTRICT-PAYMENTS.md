[Signifyd Extension for Magento 2](../README.md) > Restrict orders by payment methods

# Restrict orders by payment methods

**_Warning: These steps should only be performed under the instruction and supervision of Signifyd. If these steps are not completed correctly you may experience issues with the Signifyd extension and or your Magento store. It is recommended to test this on a development environment first._**

## Things to know before getting started

Orders placed using a specific payment method can be excluded from being sent to Signifyd. These orders will not be created in Signifyd and the extension will not interfere with the order workflow i.e. place the order on hold or capture the payment.

By default the extension will automatically exclude orders with the following payment methods from being sent to Signifyd  `checkmo,cashondelivery,banktransfer,purchaseorder`. If you want to modify the payment methods (add or remove) you will need to provide a list of payment methods codes in a comma separated list. You will also need to clear the configuration or full cache for the change to take effect.

### Add custom payment methods

Insert the list of payment codes you want to restict and then run the command below on your database:

```
INSERT INTO core_config_data (path, value) VALUES ('signifyd/general/restrict_payment_methods', 'INSERT-LIST-OF-PAYMENT-METHODS-HERE');
```
### Update custom payment methods

To modify an existing restricted list, insert the list of new payment codes you want to restict and then run the following command on your database:

```
UPDATE core_config_data SET value='INSERT-LIST-OF-PAYMENT-METHODS-HERE' WHERE path='signifyd/general/restrict_payment_methods';
```
### Delete custom payment methods

To revert back to the extension's default restricted payment methods, just delete it from the database:

```
DELETE FROM core_config_data WHERE path='signifyd/general/restrict_payment_methods';
```

### Check current restriction settings

To check the current restricted payment methods, run the command below on your database:

```
SELECT * FROM core_config_data WHERE path LIKE 'signifyd/general/restrict%';
