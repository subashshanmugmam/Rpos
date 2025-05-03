-- Retail POS System Database Schema
-- Create database
CREATE DATABASE IF NOT EXISTS retail_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE retail_pos;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    role ENUM('admin', 'salesperson', 'stock_manager') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    last_logout TIMESTAMP NULL
);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, full_name, email, role) VALUES 
('admin', 'admin123', 'System Administrator', 'admin@retailpos.com', 'admin'),
('sales', 'sales123', 'Sales Person', 'sales@retailpos.com', 'salesperson'),
('stock', 'stock123', 'Stock Manager', 'stock@retailpos.com', 'stock_manager');

-- Categories Table
CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Insert some default categories
INSERT INTO categories (name, description) VALUES 
('Electronics', 'Electronic items like phones, laptops, etc.'),
('Groceries', 'Food items, beverages, etc.'),
('Clothing', 'Apparel items like shirts, pants, etc.'),
('Home Appliances', 'Home appliances like refrigerators, washing machines, etc.'),
('Stationery', 'Office supplies, notebooks, pens, etc.');

-- Suppliers Table
CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample suppliers
INSERT INTO suppliers (name, contact_person, email, phone, address) VALUES 
('ABC Electronics', 'John Smith', 'john@abcelectronics.com', '555-123-4567', '123 Main St, Anytown'),
('XYZ Distributors', 'Jane Doe', 'jane@xyzdist.com', '555-987-6543', '456 Oak Ave, Somewhere'),
('Best Supplies', 'Bob Johnson', 'bob@bestsupplies.com', '555-456-7890', '789 Pine Rd, Elsewhere');

-- Products Table
CREATE TABLE IF NOT EXISTS products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sku VARCHAR(30) UNIQUE,
    description TEXT,
    category_id INT,
    supplier_id INT,
    cost_price DECIMAL(10, 2) NOT NULL,
    selling_price DECIMAL(10, 2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    minimum_stock INT NOT NULL DEFAULT 10,
    image_path VARCHAR(255),
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE SET NULL
);

-- Insert sample products
INSERT INTO products (name, sku, description, category_id, supplier_id, cost_price, selling_price, stock_quantity, minimum_stock) VALUES 
('Smartphone X', 'PHN-001', 'Latest smartphone with advanced features', 1, 1, 400.00, 599.99, 25, 5),
('Laptop Pro', 'LPT-002', 'Professional laptop with high performance', 1, 1, 800.00, 1299.99, 15, 3),
('Tea - Premium', 'GRO-001', 'High quality tea leaves', 2, 2, 3.50, 5.99, 50, 10),
('Coffee - Arabica', 'GRO-002', 'Premium Arabica coffee beans', 2, 2, 7.00, 12.99, 40, 8),
('T-Shirt - Large', 'CLT-001', 'Cotton T-shirt, large size', 3, 3, 8.00, 15.99, 30, 10),
('Jeans - Medium', 'CLT-002', 'Denim jeans, medium size', 3, 3, 20.00, 39.99, 25, 5),
('Notebook - Lined', 'STN-001', 'A4 lined notebook, 100 pages', 5, 3, 1.50, 3.99, 60, 20),
('Ballpoint Pens (Pack of 10)', 'STN-002', 'Pack of 10 blue ballpoint pens', 5, 3, 2.00, 4.99, 45, 15);

-- Customers Table
CREATE TABLE IF NOT EXISTS customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample customers
INSERT INTO customers (first_name, last_name, email, phone, address) VALUES 
('Mike', 'Johnson', 'mike@example.com', '555-111-2222', '123 Oak St, Somewhere'),
('Sarah', 'Williams', 'sarah@example.com', '555-333-4444', '456 Maple Ave, Somewhere'),
('David', 'Brown', 'david@example.com', '555-555-6666', '789 Pine Blvd, Somewhere');

-- Discounts Table
CREATE TABLE IF NOT EXISTS discounts (
    discount_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    value DECIMAL(10, 2) NOT NULL,
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample discounts
INSERT INTO discounts (name, description, discount_type, value, start_date, end_date) VALUES 
('Summer Sale', '15% off on all products', 'percentage', 15.00, '2025-04-01', '2025-05-31'),
('Clearance', 'Fixed $10 discount on selected items', 'fixed', 10.00, '2025-04-01', '2025-04-30');

-- Sales Transactions Table
CREATE TABLE IF NOT EXISTS sales_transactions (
    sale_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(20) NOT NULL UNIQUE,
    customer_id INT,
    salesperson_id INT NOT NULL,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    subtotal DECIMAL(10, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) NOT NULL,
    discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'credit_card', 'debit_card', 'mobile_payment') NOT NULL,
    payment_status ENUM('paid', 'pending', 'cancelled') NOT NULL DEFAULT 'paid',
    notes TEXT,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL,
    FOREIGN KEY (salesperson_id) REFERENCES users(user_id)
);

-- Sales Items Table (products in each sale)
CREATE TABLE IF NOT EXISTS sales_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
    total_price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales_transactions(sale_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- Stock Movements Table (for inventory tracking)
CREATE TABLE IF NOT EXISTS stock_movements (
    movement_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    movement_type ENUM('in', 'out', 'adjustment') NOT NULL,
    reference_id INT,
    movement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    performed_by INT NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    FOREIGN KEY (performed_by) REFERENCES users(user_id)
);

-- Settings Table
CREATE TABLE IF NOT EXISTS settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Payment Methods Table
CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    is_active BOOLEAN NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default settings and payments
INSERT INTO settings (setting_key, setting_value, description) VALUES
('company_name', 'Retail POS System', 'Name of the company'),
('company_address', '123 Business Street, City, Country', 'Address of the company'),
('company_phone', '+1-555-123-4567', 'Contact phone number'),
('company_email', 'info@retailpos.com', 'Contact email address'),
('tax_rate', '5.0', 'Default tax rate in percentage'),
('receipt_footer', 'Thank you for shopping with us!', 'Text to display at the bottom of receipts'),
('currency', 'USD', 'Default currency'),
('barcode_prefix', 'RETAIL', 'Prefix for generated barcodes');

INSERT INTO payments (name, description) VALUES
('Cash', 'Physical currency payments'),
('Credit Card', 'Visa/Mastercard payments'),
('Mobile Wallet', 'Digital wallet payments');

-- AI Predictions Table
CREATE TABLE IF NOT EXISTS ai_predictions (
    prediction_id INT AUTO_INCREMENT PRIMARY KEY,
    prediction_date DATE NOT NULL,
    prediction_value VARCHAR(255) NOT NULL,
    prediction_accuracy DECIMAL(5, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Audit Log Table (for tracking important system actions)
CREATE TABLE IF NOT EXISTS audit_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    log_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Create indexes for better performance
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_supplier ON products(supplier_id);
CREATE INDEX idx_sales_customer ON sales_transactions(customer_id);
CREATE INDEX idx_sales_salesperson ON sales_transactions(salesperson_id);
CREATE INDEX idx_sales_date ON sales_transactions(sale_date);
CREATE INDEX idx_sales_items_product ON sales_items(product_id);
CREATE INDEX idx_stock_movements_product ON stock_movements(product_id);