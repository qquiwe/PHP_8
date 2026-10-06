CREATE DATABASE IF NOT EXISTS practicum4 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE practicum4;

CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    amount DECIMAL(12, 2) NOT NULL,
    category VARCHAR(100) NOT NULL,
    transaction_date DATE NOT NULL,
    owner_token VARCHAR(64) NULL,          -- кому належить запис
    KEY idx_transactions_category (category),
    KEY idx_transactions_owner_token (owner_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
