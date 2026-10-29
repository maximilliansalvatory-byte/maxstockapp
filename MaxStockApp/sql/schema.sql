-- MaxStockApp Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- Character set: utf8mb4 for full Unicode + Swahili support

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS maxstockapp
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE maxstockapp;

-- =====================================================
-- 1. BUSINESSES (Merchants)
-- =====================================================
CREATE TABLE businesses (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    slug            VARCHAR(160) NOT NULL UNIQUE,
    business_type   ENUM('supermarket','restaurant','retail','other') DEFAULT 'retail',
    phone           VARCHAR(20) NOT NULL,
    email           VARCHAR(150) NULL,
    address         TEXT NULL,
    city            VARCHAR(80) DEFAULT 'Dar es Salaam',
    tin_number      VARCHAR(30) NULL,          -- Tanzania TIN
    logo_url        VARCHAR(255) NULL,
    status          ENUM('active','suspended','pending') DEFAULT 'pending',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_city (city)
) ENGINE=InnoDB;

-- =====================================================
-- 2. BRANCHES (Multi-branch support)
-- =====================================================
CREATE TABLE branches (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     INT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL,
    code            VARCHAR(20) NOT NULL,       -- e.g. DSM-01
    phone           VARCHAR(20) NULL,
    address         TEXT NULL,
    city            VARCHAR(80) DEFAULT 'Dar es Salaam',
    latitude        DECIMAL(10,8) NULL,
    longitude       DECIMAL(11,8) NULL,
    is_main         TINYINT(1) DEFAULT 0,
    status          ENUM('active','inactive') DEFAULT 'active',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_business_code (business_id, code),
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business (business_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- =====================================================
-- 3. USERS (Admins, Staff, Customers, Super Admin)
-- =====================================================
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     INT UNSIGNED NULL,         -- NULL for Super Admin & pure customers
    branch_id       INT UNSIGNED NULL,         -- Primary branch for staff
    role            ENUM('super_admin','business_admin','staff','customer') NOT NULL,
    full_name       VARCHAR(120) NOT NULL,
    phone           VARCHAR(20) NOT NULL UNIQUE,
    email           VARCHAR(150) NULL,
    password_hash   VARCHAR(255) NOT NULL,
    avatar_url      VARCHAR(255) NULL,
    language        ENUM('en','sw') DEFAULT 'en',
    status          ENUM('active','suspended','pending') DEFAULT 'active',
    last_login_at   DATETIME NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    INDEX idx_role (role),
    INDEX idx_business (business_id),
    INDEX idx_phone (phone),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- =====================================================
-- 4. STAFF PERMISSIONS (Fine-grained RBAC)
-- =====================================================
CREATE TABLE staff_permissions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    permission_key  VARCHAR(60) NOT NULL,      -- e.g. products.create, orders.view, stock.update
    allowed         TINYINT(1) DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_perm (user_id, permission_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 5. CATEGORIES
-- =====================================================
CREATE TABLE categories (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     INT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL,
    name_sw         VARCHAR(100) NULL,         -- Swahili name
    slug            VARCHAR(110) NOT NULL,
    parent_id       INT UNSIGNED NULL,
    sort_order      INT DEFAULT 0,
    status          ENUM('active','inactive') DEFAULT 'active',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_business_slug (business_id, slug),
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_business (business_id)
) ENGINE=InnoDB;

-- =====================================================
-- 6. PRODUCTS
-- =====================================================
CREATE TABLE products (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     INT UNSIGNED NOT NULL,
    category_id     INT UNSIGNED NULL,
    name            VARCHAR(180) NOT NULL,
    name_sw         VARCHAR(180) NULL,
    description     TEXT NULL,
    description_sw  TEXT NULL,
    sku             VARCHAR(50) NULL,
    barcode         VARCHAR(50) NULL,
    unit            VARCHAR(30) DEFAULT 'pcs', -- pcs, kg, litre, pack, etc.
    price           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cost_price      DECIMAL(12,2) NULL,         -- for margin calculation
    tax_rate        DECIMAL(5,2) DEFAULT 0.00, -- e.g. 18.00 for VAT
    image_url       VARCHAR(255) NULL,
    has_variants    TINYINT(1) DEFAULT 0,
    expiry_tracking TINYINT(1) DEFAULT 0,
    low_stock_threshold INT DEFAULT 5,
    status          ENUM('active','inactive','out_of_stock') DEFAULT 'active',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_business (business_id),
    INDEX idx_category (category_id),
    INDEX idx_barcode (barcode),
    INDEX idx_sku (sku),
    INDEX idx_status (status),
    FULLTEXT idx_search (name, name_sw, description)
) ENGINE=InnoDB;

-- =====================================================
-- 7. PRODUCT VARIANTS (size, flavor, weight, etc.)
-- =====================================================
CREATE TABLE product_variants (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id      INT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL,      -- e.g. "500ml", "Large", "Chicken"
    sku             VARCHAR(50) NULL,
    barcode         VARCHAR(50) NULL,
    price           DECIMAL(12,2) NOT NULL,
    cost_price      DECIMAL(12,2) NULL,
    status          ENUM('active','inactive') DEFAULT 'active',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

-- =====================================================
-- 8. STOCK LEVELS (per branch)
-- =====================================================
CREATE TABLE stock_levels (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id       INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED NOT NULL,
    variant_id      INT UNSIGNED NULL,
    quantity        DECIMAL(12,3) NOT NULL DEFAULT 0,  -- support fractional for kg/litre
    reserved_qty    DECIMAL(12,3) DEFAULT 0,           -- held in pending orders
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_branch_product_variant (branch_id, product_id, variant_id),
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    INDEX idx_product (product_id),
    INDEX idx_low_stock (quantity)
) ENGINE=InnoDB;

-- =====================================================
-- 9. STOCK MOVEMENTS (audit trail)
-- =====================================================
CREATE TABLE stock_movements (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id       INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED NOT NULL,
    variant_id      INT UNSIGNED NULL,
    movement_type   ENUM('restock','sale','return','wastage','adjustment','transfer_in','transfer_out') NOT NULL,
    quantity        DECIMAL(12,3) NOT NULL,             -- positive or negative
    quantity_before DECIMAL(12,3) NOT NULL,
    quantity_after  DECIMAL(12,3) NOT NULL,
    reference_type  VARCHAR(40) NULL,                  -- order, manual, etc.
    reference_id    INT UNSIGNED NULL,
    notes           TEXT NULL,
    performed_by    INT UNSIGNED NULL,                  -- user_id
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_branch_date (branch_id, created_at),
    INDEX idx_product (product_id),
    INDEX idx_type (movement_type)
) ENGINE=InnoDB;

-- =====================================================
-- 10. ORDERS
-- =====================================================
CREATE TABLE orders (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number    VARCHAR(30) NOT NULL UNIQUE,       -- e.g. MAX-20260820-0001
    business_id     INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    customer_id     INT UNSIGNED NULL,                 -- NULL for walk-in POS
    order_type      ENUM('online','pos','delivery') DEFAULT 'online',
    status          ENUM('pending','paid','fulfilled','completed','cancelled','refunded') DEFAULT 'pending',
    payment_status  ENUM('unpaid','pending','paid','failed','refunded') DEFAULT 'unpaid',
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount      DECIMAL(12,2) DEFAULT 0.00,
    discount_amount DECIMAL(12,2) DEFAULT 0.00,
    total_amount    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency        CHAR(3) DEFAULT 'TZS',
    discount_code   VARCHAR(40) NULL,
    pickup_code     VARCHAR(10) NULL,                  -- for in-store collection
    notes           TEXT NULL,
    fulfilled_at    DATETIME NULL,
    completed_at    DATETIME NULL,
    cancelled_at    DATETIME NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_business (business_id),
    INDEX idx_branch (branch_id),
    INDEX idx_customer (customer_id),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- 11. ORDER ITEMS
-- =====================================================
CREATE TABLE order_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED NOT NULL,
    variant_id      INT UNSIGNED NULL,
    product_name    VARCHAR(180) NOT NULL,             -- snapshot at time of order
    variant_name    VARCHAR(100) NULL,
    unit_price      DECIMAL(12,2) NOT NULL,
    quantity        DECIMAL(12,3) NOT NULL,
    tax_rate        DECIMAL(5,2) DEFAULT 0.00,
    line_total      DECIMAL(12,2) NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    INDEX idx_order (order_id)
) ENGINE=InnoDB;

-- =====================================================
-- 12. PAYMENTS
-- =====================================================
CREATE TABLE payments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    payment_ref     VARCHAR(80) NOT NULL,              -- unique provider reference
    provider        ENUM('mpesa','tigo','airtel','nmb','crdb','cash','other') NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    currency        CHAR(3) DEFAULT 'TZS',
    status          ENUM('pending','success','failed','cancelled','refunded') DEFAULT 'pending',
    provider_txn_id VARCHAR(100) NULL,                 -- external transaction ID
    phone           VARCHAR(20) NULL,                  -- payer phone
    raw_response    JSON NULL,                         -- full callback payload
    paid_at         DATETIME NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_payment_ref (payment_ref),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_provider (provider),
    INDEX idx_status (status),
    INDEX idx_provider_txn (provider_txn_id)
) ENGINE=InnoDB;

-- =====================================================
-- 13. AUDIT LOGS
-- =====================================================
CREATE TABLE audit_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NULL,
    business_id     INT UNSIGNED NULL,
    action          VARCHAR(80) NOT NULL,              -- e.g. product.create, stock.adjust
    entity_type     VARCHAR(40) NULL,                  -- products, orders, users...
    entity_id       INT UNSIGNED NULL,
    old_values      JSON NULL,
    new_values      JSON NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(255) NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_business (business_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- 14. DISCOUNT CODES (Phase 3, but schema ready)
-- =====================================================
CREATE TABLE discount_codes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     INT UNSIGNED NOT NULL,
    code            VARCHAR(40) NOT NULL,
    type            ENUM('percentage','fixed') DEFAULT 'percentage',
    value           DECIMAL(12,2) NOT NULL,
    min_order_amount DECIMAL(12,2) DEFAULT 0,
    max_uses        INT NULL,
    used_count      INT DEFAULT 0,
    starts_at       DATETIME NULL,
    ends_at         DATETIME NULL,
    status          ENUM('active','inactive') DEFAULT 'active',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_business_code (business_id, code),
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- SEED DATA (for local testing)
-- =====================================================

-- Super Admin (password: Admin@123 — change immediately)
INSERT INTO users (role, full_name, phone, email, password_hash, status)
VALUES (
    'super_admin',
    'MaxStock Super Admin',
    '255700000000',
    'admin@maxstockapp.tz',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'active'
);

-- Demo Business
INSERT INTO businesses (name, slug, business_type, phone, email, city, status)
VALUES (
    'Demo Supermarket',
    'demo-supermarket',
    'supermarket',
    '255712345678',
    'demo@maxstockapp.tz',
    'Dar es Salaam',
    'active'
);

SET @biz_id = LAST_INSERT_ID();

-- Main Branch
INSERT INTO branches (business_id, name, code, phone, city, is_main, status)
VALUES (@biz_id, 'Main Branch - Kariakoo', 'DSM-01', '255712345678', 'Dar es Salaam', 1, 'active');

SET @branch_id = LAST_INSERT_ID();

-- Business Admin (password: password)
INSERT INTO users (business_id, branch_id, role, full_name, phone, email, password_hash, status)
VALUES (
    @biz_id,
    @branch_id,
    'business_admin',
    'Demo Business Owner',
    '255712345679',
    'owner@demo.tz',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'active'
);

-- Sample Category
INSERT INTO categories (business_id, name, name_sw, slug)
VALUES (@biz_id, 'Beverages', 'Vinywaji', 'beverages');

SET @cat_id = LAST_INSERT_ID();

-- Sample Products
INSERT INTO products (business_id, category_id, name, name_sw, unit, price, cost_price, tax_rate, low_stock_threshold, status)
VALUES
(@biz_id, @cat_id, 'Coca-Cola 500ml', 'Coca-Cola 500ml', 'pcs', 1500.00, 1100.00, 18.00, 10, 'active'),
(@biz_id, @cat_id, 'Water 1.5L', 'Maji 1.5L', 'pcs', 1000.00, 700.00, 18.00, 20, 'active'),
(@biz_id, @cat_id, 'Azam Cola 500ml', 'Azam Cola 500ml', 'pcs', 1200.00, 900.00, 18.00, 15, 'active');

-- Initial stock
INSERT INTO stock_levels (branch_id, product_id, quantity)
SELECT @branch_id, id, 50 FROM products WHERE business_id = @biz_id;

SET FOREIGN_KEY_CHECKS = 1;

-- End of schema
