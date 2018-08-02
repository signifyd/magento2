/* Uninstall script to drop all Signifyd data */

DROP TABLE signifyd_connect_case;

DROP TABLE signifyd_connect_retries;

ALTER TABLE sales_order DROP COLUMN signifyd_score;
ALTER TABLE sales_order DROP COLUMN signifyd_guarantee;
ALTER TABLE sales_order DROP COLUMN signifyd_code;

ALTER TABLE sales_order_grid DROP COLUMN signifyd_score;
ALTER TABLE sales_order_grid DROP COLUMN signifyd_guarantee;
ALTER TABLE sales_order_grid DROP COLUMN signifyd_code;