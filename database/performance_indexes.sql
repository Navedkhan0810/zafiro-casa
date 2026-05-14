CREATE INDEX IF NOT EXISTS idx_products_category_status ON products (category, status);
CREATE INDEX IF NOT EXISTS idx_products_name ON products (name);
CREATE INDEX IF NOT EXISTS idx_products_created_at ON products (created_at);
CREATE INDEX IF NOT EXISTS idx_products_featured_trending ON products (featured, trending, id);
CREATE INDEX IF NOT EXISTS idx_categories_slug ON categories (slug);
CREATE INDEX IF NOT EXISTS idx_orders_user_id ON orders (user_id);
CREATE INDEX IF NOT EXISTS idx_orders_product_id ON orders (product_id);
CREATE INDEX IF NOT EXISTS idx_orders_status_date ON orders (order_status, order_date);
CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items (order_id);
CREATE INDEX IF NOT EXISTS idx_reviews_product_id ON reviews (product_id);
CREATE INDEX IF NOT EXISTS idx_reviews_user_id ON reviews (user_id);
CREATE INDEX IF NOT EXISTS idx_reviews_status_created ON reviews (status, created_at);
CREATE INDEX IF NOT EXISTS idx_users_status_created ON users (status, created_at);
CREATE INDEX IF NOT EXISTS idx_admin_notifications_stock_unique ON admin_notifications (type, source_key, is_read, is_deleted);

SET @has_category_id := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = 'category_id');
SET @sql_category_id := IF(@has_category_id > 0, 'CREATE INDEX IF NOT EXISTS idx_products_category_id ON products (category_id)', 'SELECT 1');
PREPARE stmt_category_id FROM @sql_category_id;
EXECUTE stmt_category_id;
DEALLOCATE PREPARE stmt_category_id;

SET @has_subcategory_id := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = 'subcategory_id');
SET @sql_subcategory_id := IF(@has_subcategory_id > 0, 'CREATE INDEX IF NOT EXISTS idx_products_subcategory_id ON products (subcategory_id)', 'SELECT 1');
PREPARE stmt_subcategory_id FROM @sql_subcategory_id;
EXECUTE stmt_subcategory_id;
DEALLOCATE PREPARE stmt_subcategory_id;
