-- Rename table to be more generic if needed, or just add columns to support refunds
-- Goal: Use 'ordercancel' table for both cancellations and refunds.

-- 1. Rename table to 'order_returns' (optional but better naming) OR keep as 'ordercancel' and add 'type' column.
-- Let's stick to modifying 'ordercancel' to be the single source of truth as requested.

-- Add 'type' column to distinguish between 'cancel' and 'refund'
ALTER TABLE ordercancel ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT 'cancel' AFTER id;
ALTER TABLE ordercancel ADD INDEX idx_type (type);

-- Add 'refund_amount' column if needed (for partial refunds or specific refund tracking)
ALTER TABLE ordercancel ADD COLUMN refund_amount DECIMAL(10,2) DEFAULT NULL AFTER total_amount;

-- Rename columns to be more generic (optional, but 'cancel_reason' is fine for now, we can alias it in code)
-- or we can duplicate/alias columns if strict naming is required, but keeping it simple is better.
-- We will use 'cancel_reason' for 'refund_reason', 'cancel_status' for 'refund_status'.
