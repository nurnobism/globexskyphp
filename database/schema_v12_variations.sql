-- GlobexSky Phase PR#3 — Product Variations & SKU Matrix
-- Run this after schema_v11.sql
-- ============================================================

-- Add missing columns to product_skus
ALTER TABLE `product_skus`
    ADD COLUMN IF NOT EXISTS `weight_override` DECIMAL(8,2) DEFAULT NULL AFTER `image_url`,
    ADD COLUMN IF NOT EXISTS `is_active`       TINYINT(1)  NOT NULL DEFAULT 1 AFTER `weight_override`,
    ADD COLUMN IF NOT EXISTS `created_at`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `is_active`,
    ADD COLUMN IF NOT EXISTS `updated_at`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Add sort_order and created_at to product_variations if missing
ALTER TABLE `product_variations`
    ADD COLUMN IF NOT EXISTS `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Add created_at to product_variation_options if missing
ALTER TABLE `product_variation_options`
    ADD COLUMN IF NOT EXISTS `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
