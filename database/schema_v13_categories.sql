-- schema_v13_categories.sql
-- PR #4: 3-Level Hierarchical Category System
-- Adds icon, level, commission_rate, updated_at to existing categories table.

ALTER TABLE categories
    ADD COLUMN IF NOT EXISTS icon            VARCHAR(100)   DEFAULT NULL           AFTER description,
    ADD COLUMN IF NOT EXISTS level           TINYINT UNSIGNED NOT NULL DEFAULT 1   AFTER sort_order,
    ADD COLUMN IF NOT EXISTS commission_rate DECIMAL(5,2)   DEFAULT NULL           AFTER level,
    ADD COLUMN IF NOT EXISTS updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER is_active;

-- Update level for existing rows based on parent_id depth
UPDATE categories SET level = 1 WHERE parent_id IS NULL;
UPDATE categories c
  JOIN categories p ON c.parent_id = p.id
  SET c.level = p.level + 1
WHERE c.parent_id IS NOT NULL;
