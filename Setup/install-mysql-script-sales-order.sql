ALTER TABLE sales_order ADD COLUMN signifyd_score FLOAT DEFAULT NULL;
ALTER TABLE sales_order ADD COLUMN signifyd_guarantee VARCHAR(64) NOT NULL DEFAULT 'N/A';
ALTER TABLE sales_order ADD COLUMN signifyd_code VARCHAR(255) NOT NULL DEFAULT '';

ALTER TABLE sales_order_grid ADD COLUMN signifyd_score FLOAT DEFAULT NULL;
ALTER TABLE sales_order_grid ADD COLUMN signifyd_guarantee VARCHAR(64) NOT NULL DEFAULT 'N/A';
ALTER TABLE sales_order_grid ADD COLUMN signifyd_code VARCHAR(255) NOT NULL DEFAULT '';