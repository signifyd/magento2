# Database Inconsistency

### Inconsistency

When upgrading to the newest versions, some merchants are not having the field signifyd_connect_case.order_id updated properly due to some unexpected errors on the upgrade scripts (e.g.: very large databases). This causes the extension to misunderstand that some orders already have cases and create new ones.

### Fix

Run below command on the database:

```sql
UPDATE signifyd_connect_case JOIN sales_order
    ON signifyd_connect_case.order_increment=sales_order.increment_id
SET signifyd_connect_case.order_id=sales_order.entity_id;
```

### Mark the issue as fixed

After successfully update the database, wait for the extension to check the database consistency overnight. You can hide the admin message by clicking on mark as fixed link on Magento admin.

If you're having issue with the overnight verification, you can run the below command to forcibly mark it as fixed. Warning: after this the extension will not check for the database inconsistency anymore.

```sql
DELETE FROM core_config_data WHERE path='signifyd/general/upgrade4.3_inconsistency';
INSERT INTO core_config_data (path, value) VALUES ('signifyd/general/upgrade4.3_inconsistency', 'fixed');
```
