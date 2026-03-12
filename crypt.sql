-- ========================================
-- DATABASE: Crypto Trading Simulation
-- ========================================
CREATE DATABASE IF NOT EXISTS crypto_simulation;
USE crypto_simulation;

-- ========================================
-- 1. Users Table
-- ========================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') DEFAULT 'user',
    balance DECIMAL(15,2) DEFAULT 10000.00,
    status ENUM('active','banned') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- 2. Cryptocurrencies Table
-- ========================================
CREATE TABLE cryptocurrencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    symbol VARCHAR(10) NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    market_cap DECIMAL(20,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- 3. Wallets Table
-- ========================================
CREATE TABLE wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    crypto_id INT NOT NULL,
    amount DECIMAL(18,8) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (crypto_id) REFERENCES cryptocurrencies(id) ON DELETE CASCADE
);

-- ========================================
-- 4. Trades Table
-- ========================================
CREATE TABLE trades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    crypto_id INT NOT NULL,
    type ENUM('buy','sell') NOT NULL,
    amount DECIMAL(18,8) NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    total DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (crypto_id) REFERENCES cryptocurrencies(id) ON DELETE CASCADE
);

-- ========================================
-- 5. Transactions Table (Deposits/Withdrawals/Adjustments)
-- ========================================
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('deposit','withdraw','adjustment') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================
-- 6. Price History Table
-- ========================================
CREATE TABLE price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    crypto_id INT NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (crypto_id) REFERENCES cryptocurrencies(id) ON DELETE CASCADE
);

-- ========================================
-- 7. Admin Logs Table
-- ========================================
CREATE TABLE admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================
-- 8. Referrals Table (Optional)
-- ========================================
CREATE TABLE referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL,
    referred_id INT NOT NULL,
    bonus DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================
-- 9. Default Admin User
-- ========================================
INSERT INTO users (name, email, password, role, balance)
VALUES ('Admin User', 'admin@crypto.com', '$2y$10$ExampleHashForAdmin1234567890', 'admin', 0.00);

-- ========================================
-- 10. Default Cryptocurrencies
-- ========================================
INSERT INTO cryptocurrencies (name, symbol, price, market_cap)
VALUES
('Bitcoin','BTC',65000, 1200000000000),
('Ethereum','ETH',3500, 400000000000),
('Solana','SOL',120, 36000000000),
('BNB','BNB',550, 90000000000),
('Cardano','ADA',0.50, 16000000000);

-- ========================================
-- 11. Example User (Optional)
-- ========================================
INSERT INTO users (name, email, password, role, balance)
VALUES ('Test User','user@crypto.com','$2y$10$ExampleHashForUser1234567890','user',10000.00);

-- ========================================
-- 12. Indexes for Performance
-- ========================================
CREATE INDEX idx_user_email ON users(email);
CREATE INDEX idx_trade_user ON trades(user_id);
CREATE INDEX idx_wallet_user ON wallets(user_id);
CREATE INDEX idx_price_crypto ON price_history(crypto_id);