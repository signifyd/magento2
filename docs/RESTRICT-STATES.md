[Signifyd Extension for Magento 2](../README.md) > Restrict orders by states

# Restrict orders by states

Orders with a specific state can be excluded from being sent to Signifyd. E.g. by default, the extension restricts any action on payment_review state, to ensure the extension does not interfere with the payment workflow.

**_Warning: the wrong settings can interfere with the checkout and payment workflows. If you need to modify the restricted states it's recommended you first test it on your development environment. The default restricted states have already been tested with Magento's default payment methods._**

## Things to know before getting started
Be aware that these settings use Magento states (not status), which must be one of these: `new, pending_payment, payment_review, processing, complete, closed, canceled, holded`. States should be provided as a comma separated list of one or more values. You will also need to clear the configuration or full cache. 

## Changing settings

Use the command below on the database to change settings. Replace `holded,pending_payment,payment_review,canceled,closed,complete` with the desired states. 

### Add custom states

To include custom create states use the command below on your database:

```
INSERT INTO core_config_data (path, value) VALUES ('signifyd/general/restrict_states_create', 'holded,pending_payment,payment_review,canceled,closed,complete');
```

### Update custom states

To modify an existing custom state, use the command below:

```
UPDATE core_config_data SET value='holded,pending_payment,payment_review,canceled,closed,complete' WHERE path='signifyd/general/restrict_states_create';
```

### Delete custom states

To use the extension default states, use the command below:

```
DELETE FROM core_config_data WHERE path='signifyd/general/restrict_states_create';
```

### Checking current restriction settings

To check the current custom state settings, run the command below:

```
SELECT * FROM core_config_data WHERE path LIKE 'signifyd/general/restrict%';
```
