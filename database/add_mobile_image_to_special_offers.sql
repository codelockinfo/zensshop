-- Migration: Add mobile_image column to special_offers table
-- Used for responsive banners/cards in special offers section

ALTER TABLE `special_offers` 
ADD COLUMN `mobile_image` VARCHAR(255) DEFAULT NULL AFTER `image`;
