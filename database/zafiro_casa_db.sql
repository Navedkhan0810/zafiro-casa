CREATE DATABASE IF NOT EXISTS zafiro_casa_db
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE zafiro_casa_db;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+05:30";

CREATE TABLE IF NOT EXISTS admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    username VARCHAR(80) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    email VARCHAR(160) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    gender VARCHAR(30) NULL,
    profile_image VARCHAR(255) NULL,
    profile_image_position_x DECIMAL(5,2) DEFAULT 50,
    profile_image_position_y DECIMAL(5,2) DEFAULT 50,
    profile_image_zoom DECIMAL(4,2) DEFAULT 1,
    password VARCHAR(255) NOT NULL,
    address TEXT NULL,
    city VARCHAR(80) NULL,
    state VARCHAR(80) NULL,
    pincode VARCHAR(20) NULL,
    status VARCHAR(20) DEFAULT 'active',
    is_blocked TINYINT(1) DEFAULT 0,
    is_deleted TINYINT(1) DEFAULT 0,
    deleted_at DATETIME NULL,
    last_login DATETIME NULL,
    remember_token_hash VARCHAR(255) NULL,
    remember_token_expires DATETIME NULL,
    reset_otp VARCHAR(10) NULL,
    reset_otp_expiry DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(120) NOT NULL UNIQUE,
    slug VARCHAR(160) NOT NULL UNIQUE,
    description TEXT NULL,
    category_image VARCHAR(255) NULL,
    is_main TINYINT(1) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hero_slides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    category VARCHAR(120) NOT NULL,
    image VARCHAR(255) NULL,
    image_1 VARCHAR(255) NULL,
    image_2 VARCHAR(255) NULL,
    image_3 VARCHAR(255) NULL,
    image_4 VARCHAR(255) NULL,
    description TEXT NULL,
    slug VARCHAR(160) NULL,
    brand VARCHAR(120) NULL,
    sku VARCHAR(80) NULL,
    original_price DECIMAL(10,2) DEFAULT 0,
    discount_price DECIMAL(10,2) DEFAULT 0,
    stock_quantity INT DEFAULT 0,
    short_description TEXT NULL,
    full_description TEXT NULL,
    specifications TEXT NULL,
    material VARCHAR(120) NULL,
    color VARCHAR(80) NULL,
    dimensions VARCHAR(120) NULL,
    weight VARCHAR(80) NULL,
    seating_capacity VARCHAR(80) NULL,
    room_type VARCHAR(120) NULL,
    assembly_required VARCHAR(20) DEFAULT 'No',
    featured TINYINT(1) DEFAULT 0,
    trending TINYINT(1) DEFAULT 0,
    in_stock TINYINT(1) DEFAULT 1,
    status VARCHAR(20) DEFAULT 'active',
    gallery_images TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_products_category (category),
    INDEX idx_products_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_featured TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_images_product_id (product_id),
    INDEX idx_product_images_main (is_main)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(40) NULL,
    order_code VARCHAR(40) NULL,
    user_id INT NULL,
    product_id INT NULL,
    customer_name VARCHAR(120) NULL,
    customer_email VARCHAR(160) NULL,
    customer_phone VARCHAR(30) NULL,
    customer_contact VARCHAR(30) NULL,
    delivery_address TEXT NULL,
    payment_status VARCHAR(50) DEFAULT 'Pending',
    order_status VARCHAR(50) DEFAULT 'Pending',
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    confirmed_date DATETIME NULL,
    shipping_date DATETIME NULL,
    delivery_date DATETIME NULL,
    return_date DATETIME NULL,
    refund_date DATETIME NULL,
    product_name VARCHAR(180) NULL,
    quantity INT DEFAULT 1,
    payment_method VARCHAR(80) NULL,
    total DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_orders_order_id (order_id),
    INDEX idx_orders_order_code (order_code),
    INDEX idx_orders_user_id (user_id),
    INDEX idx_orders_status (order_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(40) NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(180) NULL,
    product_image VARCHAR(255) NULL,
    quantity INT DEFAULT 1,
    price DECIMAL(10,2) DEFAULT 0,
    subtotal DECIMAL(10,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_items_order_id (order_id),
    INDEX idx_order_items_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(40) NULL,
    user_id INT NULL,
    reason VARCHAR(160) NULL,
    comment TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_returns_order_id (order_id),
    INDEX idx_order_returns_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    order_id VARCHAR(60) NOT NULL,
    rating INT NOT NULL,
    review_text TEXT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_order_product_review (user_id, product_id, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    notifications TINYINT(1) DEFAULT 1,
    privacy_options TINYINT(1) DEFAULT 0,
    account_preferences TINYINT(1) DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(40) DEFAULT 'system',
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    house_no VARCHAR(120) NOT NULL,
    street_area VARCHAR(180) NOT NULL,
    city VARCHAR(80) NOT NULL,
    state VARCHAR(80) NOT NULL,
    pincode VARCHAR(20) NOT NULL,
    landmark VARCHAR(180) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_addresses_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO admins (name, email, username, password)
SELECT 'Zafiro Admin', 'zafirocasaadmin@gmail.com', 'admin', '$2y$10$DezBM5H/pry3L3KzViBwiOUtyNhnMidbr15jQAWy6i1SvxEOFpqn2'
WHERE NOT EXISTS (
    SELECT 1 FROM admins WHERE username = 'admin' OR email = 'zafirocasaadmin@gmail.com'
);

INSERT INTO categories (category_name, slug, description, is_featured, status)
SELECT * FROM (
    SELECT 'Sofas' AS category_name, 'sofas' AS slug, 'Premium sofas and seating for elegant living rooms.' AS description, 1 AS is_featured, 'active' AS status
    UNION ALL SELECT 'Living', 'living', 'Living room furniture and comfort essentials.', 1, 'active'
    UNION ALL SELECT 'Bedroom', 'bedroom', 'Bedroom furniture for refined comfort.', 1, 'active'
    UNION ALL SELECT 'Mattress', 'mattress', 'Mattresses, pillows and sleep accessories.', 0, 'active'
    UNION ALL SELECT 'Dining', 'dining', 'Dining tables, chairs and complete dining sets.', 1, 'active'
    UNION ALL SELECT 'Storage', 'storage', 'Wardrobes, TV units and home storage.', 1, 'active'
    UNION ALL SELECT 'Study & Office', 'study-office', 'Study tables, office chairs and work furniture.', 0, 'active'
    UNION ALL SELECT 'Outdoor', 'outdoor', 'Outdoor and balcony furniture.', 0, 'active'
    UNION ALL SELECT 'Decor & Furnishing', 'decor-furnishing', 'Decor, lamps, mirrors and furnishings.', 0, 'active'
    UNION ALL SELECT 'Modular Kitchen', 'modular-kitchen', 'Modern modular kitchen solutions.', 0, 'active'
    UNION ALL SELECT 'Tables', 'tables', 'Coffee tables, side tables and dining tables.', 0, 'active'
    UNION ALL SELECT 'Chairs', 'chairs', 'Accent, dining and office chairs.', 0, 'active'
) AS category_seed
WHERE NOT EXISTS (
    SELECT 1 FROM categories WHERE categories.slug = category_seed.slug
);

INSERT INTO products
(name, price, category, image, description, slug, brand, sku, original_price, discount_price, stock_quantity, short_description, full_description, featured, trending, in_stock, status)
SELECT * FROM (
    SELECT 'Royal Comfort Sofa' AS name, 28999.00 AS price, 'sofa' AS category, 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=600&q=80' AS image, 'Premium upholstered sofa for elegant living rooms.' AS description, 'royal-comfort-sofa' AS slug, 'Zafiro Casa' AS brand, 'ZC-SOF-001' AS sku, 32999.00 AS original_price, 28999.00 AS discount_price, 8 AS stock_quantity, 'Premium upholstered sofa for elegant living rooms.' AS short_description, 'Premium upholstered sofa for elegant living rooms.' AS full_description, 1 AS featured, 1 AS trending, 1 AS in_stock, 'active' AS status
    UNION ALL SELECT 'Classic Beige Sofa', 31499.00, 'sofa', 'https://images.unsplash.com/photo-1493663284031-b7e3aefcae8e?auto=format&fit=crop&w=600&q=80', 'Soft beige sofa with a luxury modern finish.', 'classic-beige-sofa', 'Zafiro Casa', 'ZC-SOF-002', 35999.00, 31499.00, 6, 'Soft beige sofa with a luxury modern finish.', 'Soft beige sofa with a luxury modern finish.', 1, 0, 1, 'active'
    UNION ALL SELECT 'Modern Lounge Sofa', 35999.00, 'sofa', 'https://images.unsplash.com/photo-1616486029423-aaa4789e8c9a?auto=format&fit=crop&w=600&q=80', 'Spacious lounge sofa for premium comfort.', 'modern-lounge-sofa', 'Zafiro Casa', 'ZC-SOF-003', 39999.00, 35999.00, 5, 'Spacious lounge sofa for premium comfort.', 'Spacious lounge sofa for premium comfort.', 0, 1, 1, 'active'
    UNION ALL SELECT 'Velvet Three Seater Sofa', 42999.00, 'sofa', 'https://images.unsplash.com/photo-1540574163026-643ea20ade25?auto=format&fit=crop&w=600&q=80', 'Elegant velvet sofa with rich seating comfort.', 'velvet-three-seater-sofa', 'Zafiro Casa', 'ZC-SOF-004', 48999.00, 42999.00, 4, 'Elegant velvet sofa with rich seating comfort.', 'Elegant velvet sofa with rich seating comfort.', 0, 0, 1, 'active'
    UNION ALL SELECT 'King Size Wooden Bed', 39999.00, 'bed', 'https://images.unsplash.com/photo-1505693314120-0d443867891c?auto=format&fit=crop&w=600&q=80', 'Solid wooden king bed with refined craftsmanship.', 'king-size-wooden-bed', 'Zafiro Casa', 'ZC-BED-001', 44999.00, 39999.00, 7, 'Solid wooden king bed with refined craftsmanship.', 'Solid wooden king bed with refined craftsmanship.', 1, 1, 1, 'active'
    UNION ALL SELECT 'Queen Storage Bed', 34999.00, 'bed', 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=600&q=80', 'Queen bed with smart under-bed storage.', 'queen-storage-bed', 'Zafiro Casa', 'ZC-BED-002', 38999.00, 34999.00, 6, 'Queen bed with smart under-bed storage.', 'Queen bed with smart under-bed storage.', 1, 0, 1, 'active'
    UNION ALL SELECT 'Premium Upholstered Bed', 44999.00, 'bed', 'https://images.unsplash.com/photo-1616594039964-ae9021a400a0?auto=format&fit=crop&w=600&q=80', 'Luxury upholstered bed for a calm bedroom.', 'premium-upholstered-bed', 'Zafiro Casa', 'ZC-BED-003', 49999.00, 44999.00, 4, 'Luxury upholstered bed for a calm bedroom.', 'Luxury upholstered bed for a calm bedroom.', 0, 1, 1, 'active'
    UNION ALL SELECT 'Minimal Platform Bed', 29999.00, 'bed', 'https://images.unsplash.com/photo-1615873968403-89e068629265?auto=format&fit=crop&w=600&q=80', 'Clean platform bed with modern lines.', 'minimal-platform-bed', 'Zafiro Casa', 'ZC-BED-004', 33999.00, 29999.00, 8, 'Clean platform bed with modern lines.', 'Clean platform bed with modern lines.', 0, 0, 1, 'active'
    UNION ALL SELECT 'Six Seater Dining Set', 46999.00, 'dining', 'https://images.unsplash.com/photo-1604578762246-41134e37f9cc?auto=format&fit=crop&w=600&q=80', 'Elegant six seater dining set for family meals.', 'six-seater-dining-set', 'Zafiro Casa', 'ZC-DIN-001', 52999.00, 46999.00, 5, 'Elegant six seater dining set for family meals.', 'Elegant six seater dining set for family meals.', 1, 1, 1, 'active'
    UNION ALL SELECT 'Marble Top Dining Table', 52999.00, 'dining', 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?auto=format&fit=crop&w=600&q=80', 'Premium dining table with marble-inspired top.', 'marble-top-dining-table', 'Zafiro Casa', 'ZC-DIN-002', 58999.00, 52999.00, 4, 'Premium dining table with marble-inspired top.', 'Premium dining table with marble-inspired top.', 1, 0, 1, 'active'
    UNION ALL SELECT 'Compact Dining Set', 24999.00, 'dining', 'https://images.unsplash.com/photo-1600210492493-0946911123ea?auto=format&fit=crop&w=600&q=80', 'Compact dining solution for modern apartments.', 'compact-dining-set', 'Zafiro Casa', 'ZC-DIN-003', 28999.00, 24999.00, 7, 'Compact dining solution for modern apartments.', 'Compact dining solution for modern apartments.', 0, 1, 1, 'active'
    UNION ALL SELECT 'Wooden Dining Bench Set', 37999.00, 'dining', 'https://images.unsplash.com/photo-1540932239986-30128078f3c5?auto=format&fit=crop&w=600&q=80', 'Warm wooden dining set with bench seating.', 'wooden-dining-bench-set', 'Zafiro Casa', 'ZC-DIN-004', 41999.00, 37999.00, 6, 'Warm wooden dining set with bench seating.', 'Warm wooden dining set with bench seating.', 0, 0, 1, 'active'
    UNION ALL SELECT 'Luxury Accent Chair', 12999.00, 'chair', 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4?auto=format&fit=crop&w=600&q=80', 'Comfortable accent chair with premium upholstery.', 'luxury-accent-chair', 'Zafiro Casa', 'ZC-CHR-001', 14999.00, 12999.00, 10, 'Comfortable accent chair with premium upholstery.', 'Comfortable accent chair with premium upholstery.', 1, 1, 1, 'active'
    UNION ALL SELECT 'Wingback Lounge Chair', 18999.00, 'chair', 'https://images.unsplash.com/photo-1616627561839-074385245ff6?auto=format&fit=crop&w=600&q=80', 'Classic wingback chair for reading corners.', 'wingback-lounge-chair', 'Zafiro Casa', 'ZC-CHR-002', 21999.00, 18999.00, 9, 'Classic wingback chair for reading corners.', 'Classic wingback chair for reading corners.', 0, 1, 1, 'active'
    UNION ALL SELECT 'Dining Chair Set', 16499.00, 'chair', 'https://images.unsplash.com/photo-1580480055273-228ff5388ef8?auto=format&fit=crop&w=600&q=80', 'Stylish dining chairs with durable support.', 'dining-chair-set', 'Zafiro Casa', 'ZC-CHR-003', 18999.00, 16499.00, 8, 'Stylish dining chairs with durable support.', 'Stylish dining chairs with durable support.', 0, 0, 1, 'active'
    UNION ALL SELECT 'Office Comfort Chair', 10999.00, 'chair', 'https://images.unsplash.com/photo-1503602642458-232111445657?auto=format&fit=crop&w=600&q=80', 'Ergonomic chair for study and work areas.', 'office-comfort-chair', 'Zafiro Casa', 'ZC-CHR-004', 12999.00, 10999.00, 12, 'Ergonomic chair for study and work areas.', 'Ergonomic chair for study and work areas.', 0, 0, 1, 'active'
    UNION ALL SELECT 'Premium Wardrobe', 49999.00, 'storage', 'https://images.unsplash.com/photo-1595428774223-ef52624120d2?auto=format&fit=crop&w=600&q=80', 'Spacious wardrobe with premium finish.', 'premium-wardrobe', 'Zafiro Casa', 'ZC-STR-001', 55999.00, 49999.00, 4, 'Spacious wardrobe with premium finish.', 'Spacious wardrobe with premium finish.', 1, 1, 1, 'active'
    UNION ALL SELECT 'Wooden Chest of Drawers', 21999.00, 'storage', 'https://images.unsplash.com/photo-1540932239986-30128078f3c5?auto=format&fit=crop&w=600&q=80', 'Compact drawer unit for organized rooms.', 'wooden-chest-of-drawers', 'Zafiro Casa', 'ZC-STR-002', 24999.00, 21999.00, 7, 'Compact drawer unit for organized rooms.', 'Compact drawer unit for organized rooms.', 0, 1, 1, 'active'
    UNION ALL SELECT 'Modern TV Storage Unit', 27999.00, 'storage', 'https://images.unsplash.com/photo-1618220179428-22790b461013?auto=format&fit=crop&w=600&q=80', 'TV unit with closed and open storage.', 'modern-tv-storage-unit', 'Zafiro Casa', 'ZC-STR-003', 31999.00, 27999.00, 6, 'TV unit with closed and open storage.', 'TV unit with closed and open storage.', 0, 0, 1, 'active'
    UNION ALL SELECT 'Display Cabinet', 32999.00, 'storage', 'https://images.unsplash.com/photo-1594026112284-02bb6f3352fe?auto=format&fit=crop&w=600&q=80', 'Elegant display cabinet for decor and storage.', 'display-cabinet', 'Zafiro Casa', 'ZC-STR-004', 36999.00, 32999.00, 5, 'Elegant display cabinet for decor and storage.', 'Elegant display cabinet for decor and storage.', 0, 0, 1, 'active'
) AS sample_data
WHERE NOT EXISTS (
    SELECT 1 FROM products WHERE products.name = sample_data.name
);
