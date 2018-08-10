# Signifyd Magento 2
Signifyd's extension for Magento 2.0

Signifyd’s plugin enables merchant on Magento 2 to integrate with Signifyd in minutes, automating fraud prevention and protecting them in case of chargebacks.

## Installing via Composer


### Install Errors

Version 2.4.1+  will check for all database structures needed for the extension to work correctly. This check is performed on the extension configuration section in the Magento admin.

If any database structures are missing, the extension will be disabled.

If there are warnings about missing database modifications after installation, follow the instructions below to fix the issue.

This script will create all of the necessary structures. You will need to run it directly on your MySQL database. If there are any 'duplicate column' errors during this script execution, they can be ignored.

```sql
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
  PRIMARY KEY (`order_increment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Signifyd Cases';

CREATE TABLE IF NOT EXISTS `signifyd_connect_retries` (
  `order_increment` varchar(255) NOT NULL COMMENT 'Order ID',
  `created` timestamp NULL DEFAULT NULL COMMENT 'Creation Time',
  `updated` timestamp NULL DEFAULT NULL COMMENT 'Last Attempt',
  PRIMARY KEY (`order_increment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Signifyd Retries';

ALTER TABLE sales_order ADD COLUMN signifyd_score FLOAT DEFAULT NULL;
ALTER TABLE sales_order ADD COLUMN signifyd_guarantee VARCHAR(64) NOT NULL DEFAULT 'N/A';
ALTER TABLE sales_order ADD COLUMN signifyd_code VARCHAR(255) NOT NULL DEFAULT '';

ALTER TABLE sales_order_grid ADD COLUMN signifyd_score FLOAT DEFAULT NULL;
ALTER TABLE sales_order_grid ADD COLUMN signifyd_guarantee VARCHAR(64) NOT NULL DEFAULT 'N/A';
ALTER TABLE sales_order_grid ADD COLUMN signifyd_code VARCHAR(255) NOT NULL DEFAULT '';
```

After running the script may be necessary run the command below in terminal in order to update caches.

```bash
bin/magento setup:upgrade
```

If there still warnings about missing database modifications, please, contact our support. 

## Purge all Signifyd data

If you are having issues with the install you can remove all Signifyd data on the Magento database for a clean re-install.

**All Signifyd data on Magento database will be lost.**

```sql
DROP TABLE signifyd_connect_case;

DROP TABLE signifyd_connect_retries;

ALTER TABLE sales_order DROP COLUMN signifyd_score;
ALTER TABLE sales_order DROP COLUMN signifyd_guarantee;
ALTER TABLE sales_order DROP COLUMN signifyd_code;

ALTER TABLE sales_order_grid DROP COLUMN signifyd_score;
ALTER TABLE sales_order_grid DROP COLUMN signifyd_guarantee;
ALTER TABLE sales_order_grid DROP COLUMN signifyd_code;

DELETE FROM setup_module WHERE module='Signifyd_Connect';
```
