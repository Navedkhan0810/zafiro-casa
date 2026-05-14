ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_id VARCHAR(255) NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(255) NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NULL,
    user_id INT NULL,
    payment_gateway VARCHAR(50) DEFAULT 'PhonePe',
    gateway_order_id VARCHAR(255) NULL,
    gateway_payment_id VARCHAR(255) NULL,
    transaction_id VARCHAR(255) NULL,
    amount DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'pending',
    response_json TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
