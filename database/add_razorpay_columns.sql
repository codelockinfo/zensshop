-- Add Razorpay payment columns to orders table
-- Note: MySQL doesn't support IF NOT EXISTS for ALTER TABLE
-- Run this only if columns don't exist, or it will error if columns already exist

-- Check if columns exist first, then add them
-- For MySQL, you need to check manually or use a script

-- Add razorpay_payment_id column (if it doesn't exist)
ALTER TABLE `orders` 
ADD COLUMN `razorpay_payment_id` VARCHAR(255) NULL AFTER `payment_status`;

-- Add razorpay_order_id column (if it doesn't exist)
ALTER TABLE `orders` 
ADD COLUMN `razorpay_order_id` VARCHAR(255) NULL AFTER `razorpay_payment_id`;

-- Add indexes
CREATE INDEX `idx_razorpay_payment_id` ON `orders` (`razorpay_payment_id`);
CREATE INDEX `idx_razorpay_order_id` ON `orders` (`razorpay_order_id`);

