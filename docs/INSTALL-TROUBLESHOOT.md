[Signifyd Extension for Magento 2](../README.md) > Install Troubleshoot

# Install Troubleshoot

## Stripe payments error on 3.5.0 and 3.5.1

This is a known bug that has been fixed on 3.5.2 release. Upgrade to latest version to fix it

If during installation or on checkout process an exception with message "Payment model name is not provided in config" is raised and store does not have stripe_payments payment method installed and store is using Signifyd version 3.5.0 or 3.5.1, upgrate to latest version to fix it.

## Third-party cache errors

If something does not go as expected, try to clear any additional caches on the environment (e.g. PHP APC or OPCache, Redis, Varnish).

## There is no "SIGNIFYD" session on System > Configuration

Check if the extension is enabled, by running the command line below on terminal:

```bash
cd MAGENTO_ROOT
bin/magento module:status
```

If module is disabled, enable it by using the command line below:

```bash
cd MAGENTO_ROOT
bin/magento module:enable Signifyd_Connect
```

If module does not exist, check if below directories exist:
- MAGENTO_ROOT/vendor/signifyd/module-connect
- MAGENTO_ROOT/vendor/signifyd/signifyd-php

If the above files are not present, please repeat the installation steps.

## Logs show database related errors

On the MySQL database check for the existence of 'signifyd_connect_case' table using the command below:

```
DESC signifyd_connect_case
```

Verify if you see the following columns on the table:
- order_increment
- signifyd_status
- code
- score
- guarantee
- entries_text
- created
- updated
- magento_status
- retries

If you find any missing columns or issues with the table, check if the Magento installation scripts had been ran for the latest version.  

First locate etc/module.xml file on one of these locations:

- For composer installations: MAGENTO_ROOT/vendor/signifyd/module-connect/etc/module.xml
- For manual installations: MAGENTO_ROOT/app/code/Signifyd/Connect/etc/module.xml

On file etc/module.xml check for `setup_version` property on `<module>` tag. 

```
<module name="Signifyd_Connect" setup_version="3.2.0">
    <sequence>
        <module name="Magento_Sales" />
        <module name="Magento_Payment" />
        <module name="Magento_Directory" />
        <module name="Magento_Config" />
    </sequence>
</module>
```

Run the SQL command below on MySQL:

```
SELECT * FROM setup_module WHERE module='Signifyd_Connect';
```

The results of the above command should match with `setup_version` property from module.xml file. If they do not match, run the installation steps again and make sure to clean every possible cache on Magento administration and environment.

## Database integrity check

Version 2.4.1+  will check for all database structures needed for the extension to work correctly. This check is performed on the extension configuration section in the Magento admin.

If any database structures are missing, the extension will be disabled.

If there are warnings on Magento admin, on the extension configuration section, about missing database modifications after install/update, follow the instructions below to fix the issue.

This script will create all of the necessary structures. You will need to run it directly on your MySQL database. If there are any 'duplicate column' errors during this script execution, they can be ignored.

```mysql
CREATE TABLE IF NOT EXISTS `signifyd_connect_case` (
  `order_increment` varchar(255) NOT NULL COMMENT 'Order ID',
  `signifyd_status` varchar(255) NOT NULL DEFAULT 'PENDING' COMMENT 'Signifyd Status',
  `code` varchar(255) NOT NULL COMMENT 'Code',
  `score` float DEFAULT NULL COMMENT 'Score',
  `guarantee` varchar(64) NOT NULL DEFAULT 'N/A' COMMENT 'Guarantee Status',
  `entries_text` text NOT NULL COMMENT 'Entries',
  `created` timestamp NULL DEFAULT NULL COMMENT 'Creation Time',
  `updated` timestamp NULL DEFAULT NULL COMMENT 'Update Time',
  `magento_status` varchar(255) NOT NULL DEFAULT 'waiting_submission' COMMENT 'Magento Status',
  `retries` int(11) NOT NULL DEFAULT '0' COMMENT 'Number of retries for current case magento_status',
  PRIMARY KEY (`order_increment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Signifyd Cases';

ALTER TABLE sales_order ADD COLUMN signifyd_score FLOAT DEFAULT NULL;
ALTER TABLE sales_order ADD COLUMN signifyd_guarantee VARCHAR(64) NOT NULL DEFAULT 'N/A';
ALTER TABLE sales_order ADD COLUMN signifyd_code VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE sales_order ADD COLUMN origin_store_code VARCHAR(32) DEFAULT NULL;

ALTER TABLE sales_order_grid ADD COLUMN signifyd_score FLOAT DEFAULT NULL;
ALTER TABLE sales_order_grid ADD COLUMN signifyd_guarantee VARCHAR(64) NOT NULL DEFAULT 'N/A';
ALTER TABLE sales_order_grid ADD COLUMN signifyd_code VARCHAR(255) NOT NULL DEFAULT '';
```

After running the script, run the command below in terminal in order to update caches.

```bash
bin/magento setup:upgrade
```

If there still warnings about missing database modifications, please, contact our support. 

## Purge all Signifyd data

If you are having issues with the install you can remove all Signifyd data on the Magento database for a clean re-install.

**All Signifyd data on Magento database will be lost.**

```mysql
DROP TABLE signifyd_connect_case;

ALTER TABLE sales_order DROP COLUMN signifyd_score;
ALTER TABLE sales_order DROP COLUMN signifyd_guarantee;
ALTER TABLE sales_order DROP COLUMN signifyd_code;
ALTER TABLE sales_order DROP COLUMN origin_store_code;

ALTER TABLE sales_order_grid DROP COLUMN signifyd_score;
ALTER TABLE sales_order_grid DROP COLUMN signifyd_guarantee;
ALTER TABLE sales_order_grid DROP COLUMN signifyd_code;

DELETE FROM setup_module WHERE module='Signifyd_Connect';
```

## Lock timeout

Signifyd extension use an internal lock on row level on case table to avoid race condition conflicts. The default lock timeout it is 20 seconds. If it’s needed to change this value, run below command with the desired custom value.

```
INSERT INTO core_config_data (path, value) VALUES ('signifyd/general/lock_timeout', INSERT-LOCK-TIMEOUT);
```

To modify an existing lock timeout, use the command below:

```
UPDATE core_config_data SET value=INSERT-LOCK-TIMEOUT WHERE path='signifyd/general/lock_timeout';
```

To use the extension default lock timeout, use the command below:

```
DELETE FROM core_config_data WHERE path='signifyd/general/lock_timeout';
```

To check the current custom lock timeout, run the command below:

```
SELECT * FROM core_config_data WHERE path LIKE 'signifyd/general/lock_timeout%';
```

## Failed to read auto-increment value from storage engine

If an error similar to the below one show up when upgrading the extension, copy the SQL command from the error message, remove the "AUTO_INCREMENT = XXXXXX" part and manually run the SQL on your database.

```mysql
SQLSTATE[HY000]: General error: 1467 Failed to read auto-increment value from storage engine, query was: ALTER TABLE `signifyd_connect_case` MODIFY COLUMN `code` varchar(255) NOT NULL , MODIFY COLUMN `order_increment` varchar(255) NOT NULL , MODIFY COLUMN `signifyd_status` varchar(255) NOT NULL DEFAULT "PENDING" , MODIFY COLUMN `score` float(10, 0) NULL , MODIFY COLUMN `guarantee` varchar(64) NOT NULL DEFAULT "N/A" , MODIFY COLUMN `entries_text` text NOT NULL , MODIFY COLUMN `created` timestamp NULL , MODIFY COLUMN `updated` timestamp NULL , MODIFY COLUMN `magento_status` varchar(255) NOT NULL DEFAULT "waiting_submission" , MODIFY COLUMN `retries` int NOT NULL DEFAULT 0 , ADD COLUMN `entity_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT "Entity ID", AUTO_INCREMENT = 18000000688, ADD COLUMN `origin_store_code` varchar(32) NULL DEFAULT "NULL" , ADD COLUMN `checkpoint_action_reason` text NOT NULL , ADD COLUMN `order_id` int UNSIGNED NULL , ADD COLUMN `quote_id` int UNSIGNED NULL , ADD COLUMN `checkout_token` varchar(255) NOT NULL , ADD COLUMN `policy_name` varchar(255) NOT NULL , DROP PRIMARY KEY, ADD CONSTRAINT PRIMARY KEY (`entity_id`), ADD INDEX `SIGNIFYD_CONNECT_CASE_MAGENTO_STATUS` (`magento_status`), ADD INDEX `SIGNIFYD_CONNECT_CASE_ORDER_ID` (`order_id`), ADD INDEX `SIGNIFYD_CONNECT_CASE_CODE` (`code`), COMMENT='signifyd_connect_case Table'
```

E.g. on the above error message, we would remove the "AUTO_INCREMENT = 18000000688, " part from the SQL and run the below command on database.

**Do not copy/paste the command from this documentation, use the one from your application logs, as instructed.**

```mysql
ALTER TABLE `signifyd_connect_case` MODIFY COLUMN `code` varchar(255) NOT NULL , MODIFY COLUMN `order_increment` varchar(255) NOT NULL , MODIFY COLUMN `signifyd_status` varchar(255) NOT NULL DEFAULT "PENDING" , MODIFY COLUMN `score` float(10, 0) NULL , MODIFY COLUMN `guarantee` varchar(64) NOT NULL DEFAULT "N/A" , MODIFY COLUMN `entries_text` text NOT NULL , MODIFY COLUMN `created` timestamp NULL , MODIFY COLUMN `updated` timestamp NULL , MODIFY COLUMN `magento_status` varchar(255) NOT NULL DEFAULT "waiting_submission" , MODIFY COLUMN `retries` int NOT NULL DEFAULT 0 , ADD COLUMN `entity_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT "Entity ID", ADD COLUMN `origin_store_code` varchar(32) NULL DEFAULT "NULL" , ADD COLUMN `checkpoint_action_reason` text NOT NULL , ADD COLUMN `order_id` int UNSIGNED NULL , ADD COLUMN `quote_id` int UNSIGNED NULL , ADD COLUMN `checkout_token` varchar(255) NOT NULL , ADD COLUMN `policy_name` varchar(255) NOT NULL , DROP PRIMARY KEY, ADD CONSTRAINT PRIMARY KEY (`entity_id`), ADD INDEX `SIGNIFYD_CONNECT_CASE_MAGENTO_STATUS` (`magento_status`), ADD INDEX `SIGNIFYD_CONNECT_CASE_ORDER_ID` (`order_id`), ADD INDEX `SIGNIFYD_CONNECT_CASE_CODE` (`code`), COMMENT='signifyd_connect_case Table'
```

**Do not copy/paste the command from this documentation, use the one from your application logs, as instructed.**

## All of the steps were followed but some error prevented the extension from installing succesfully

Check for any log errors on the web server (e.g. Apache, NGINX) and on PHP logs. Also check for errors on MAGENTO_ROOT/var/log on files system.log, exception.log and signifyd_connect.log. If you are still stuck you can [contact our support team](https://community.signifyd.com/support/s/)
