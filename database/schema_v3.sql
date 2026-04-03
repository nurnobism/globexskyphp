-- GlobexSky Database Schema v3
-- Phase 4: Shipment System
-- Run after schema.sql and schema_v2.sql

-- Parcel shipments
CREATE TABLE IF NOT EXISTS parcel_shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id INT,
    tracking_number VARCHAR(50) UNIQUE NOT NULL,
    status ENUM('pending','processing','picked_up','in_transit','out_for_delivery','delivered','failed','cancelled','returned') DEFAULT 'pending',
    sender_name VARCHAR(200) NOT NULL,
    sender_phone VARCHAR(20),
    sender_address TEXT NOT NULL,
    sender_city VARCHAR(100),
    sender_country VARCHAR(2),
    receiver_name VARCHAR(200) NOT NULL,
    receiver_phone VARCHAR(20),
    receiver_address TEXT NOT NULL,
    receiver_city VARCHAR(100),
    receiver_country VARCHAR(2),
    package_type ENUM('document','package','heavy') DEFAULT 'package',
    weight DECIMAL(8,2),
    length DECIMAL(8,2),
    width DECIMAL(8,2),
    height DECIMAL(8,2),
    declared_value DECIMAL(12,2),
    shipping_method ENUM('standard','express','priority','economy') DEFAULT 'standard',
    shipping_cost DECIMAL(12,2) NOT NULL,
    insurance_cost DECIMAL(12,2) DEFAULT 0,
    has_insurance TINYINT(1) DEFAULT 0,
    special_instructions TEXT,
    estimated_delivery DATE,
    actual_delivery DATETIME,
    carrier_name VARCHAR(100),
    payment_status ENUM('pending','paid','refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ps_user (user_id),
    INDEX idx_ps_order (order_id),
    INDEX idx_ps_tracking (tracking_number),
    INDEX idx_ps_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parcel tracking events
CREATE TABLE IF NOT EXISTS parcel_tracking_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    status VARCHAR(100) NOT NULL,
    location VARCHAR(200),
    description TEXT,
    event_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pte_shipment (shipment_id),
    FOREIGN KEY (shipment_id) REFERENCES parcel_shipments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User addresses
CREATE TABLE IF NOT EXISTS user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    label VARCHAR(50) DEFAULT 'Home',
    full_name VARCHAR(200) NOT NULL,
    phone VARCHAR(20),
    address_line1 VARCHAR(500) NOT NULL,
    address_line2 VARCHAR(500),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country_code VARCHAR(2) NOT NULL,
    country_name VARCHAR(100),
    is_default_shipping TINYINT(1) DEFAULT 0,
    is_default_billing TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ua_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Carrier trips (phase 4 extended version)
CREATE TABLE IF NOT EXISTS carrier_trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carrier_id INT NOT NULL,
    departure_city VARCHAR(100) NOT NULL,
    departure_country VARCHAR(2) NOT NULL,
    departure_country_name VARCHAR(100),
    arrival_city VARCHAR(100) NOT NULL,
    arrival_country VARCHAR(2) NOT NULL,
    arrival_country_name VARCHAR(100),
    travel_date DATE NOT NULL,
    return_date DATE,
    available_capacity_kg DECIMAL(8,2) NOT NULL,
    remaining_capacity_kg DECIMAL(8,2),
    price_per_kg DECIMAL(10,2) NOT NULL,
    transport_mode ENUM('flight','bus','train','car','ship') DEFAULT 'flight',
    notes TEXT,
    status ENUM('active','matched','completed','cancelled','expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ct_carrier (carrier_id),
    INDEX idx_ct_route (departure_country, arrival_country),
    INDEX idx_ct_date (travel_date),
    INDEX idx_ct_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Carry requests
CREATE TABLE IF NOT EXISTS carry_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category ENUM('document','electronics','clothing','food','medicine','other') DEFAULT 'other',
    weight_kg DECIMAL(8,2) NOT NULL,
    from_city VARCHAR(100) NOT NULL,
    from_country VARCHAR(2) NOT NULL,
    from_country_name VARCHAR(100),
    to_city VARCHAR(100) NOT NULL,
    to_country VARCHAR(2) NOT NULL,
    to_country_name VARCHAR(100),
    preferred_date_from DATE,
    preferred_date_to DATE,
    budget DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'USD',
    special_handling TEXT,
    status ENUM('open','matched','in_progress','delivered','completed','cancelled','disputed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cr_sender (sender_id),
    INDEX idx_cr_route (from_country, to_country),
    INDEX idx_cr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Carry matches
CREATE TABLE IF NOT EXISTS carry_matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    trip_id INT NOT NULL,
    carrier_id INT NOT NULL,
    sender_id INT NOT NULL,
    agreed_price DECIMAL(10,2) NOT NULL,
    platform_commission DECIMAL(10,2) NOT NULL,
    carrier_earning DECIMAL(10,2) NOT NULL,
    status ENUM('pending','accepted','pickup_scheduled','picked_up','in_transit','delivered','completed','cancelled','disputed') DEFAULT 'pending',
    pickup_time DATETIME,
    pickup_location TEXT,
    delivery_time DATETIME,
    delivery_location TEXT,
    proof_of_delivery_url VARCHAR(500),
    sender_rating DECIMAL(3,2),
    sender_review TEXT,
    carrier_rating DECIMAL(3,2),
    carrier_review TEXT,
    notes TEXT,
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cm_request (request_id),
    INDEX idx_cm_carrier (carrier_id),
    INDEX idx_cm_sender (sender_id),
    INDEX idx_cm_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Carrier earnings log (phase 4 extended)
CREATE TABLE IF NOT EXISTS carrier_earning_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carrier_id INT NOT NULL,
    match_id INT,
    type ENUM('delivery_earning','commission_deduct','payout','bonus','refund') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cel_carrier (carrier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Shipping rates configuration
CREATE TABLE IF NOT EXISTS shipping_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    origin_country VARCHAR(2) NOT NULL,
    destination_country VARCHAR(2) NOT NULL,
    base_rate DECIMAL(10,2) NOT NULL,
    rate_per_kg DECIMAL(10,2) NOT NULL,
    rate_per_cm3 DECIMAL(10,4) DEFAULT 0,
    method_multiplier_standard DECIMAL(4,2) DEFAULT 1.00,
    method_multiplier_express DECIMAL(4,2) DEFAULT 2.00,
    method_multiplier_priority DECIMAL(4,2) DEFAULT 3.00,
    method_multiplier_economy DECIMAL(4,2) DEFAULT 0.70,
    insurance_rate DECIMAL(5,2) DEFAULT 2.00,
    estimated_days_standard VARCHAR(20) DEFAULT '5-10',
    estimated_days_express VARCHAR(20) DEFAULT '2-5',
    estimated_days_priority VARCHAR(20) DEFAULT '1-3',
    estimated_days_economy VARCHAR(20) DEFAULT '10-20',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_sr_route (origin_country, destination_country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Carry service settings
CREATE TABLE IF NOT EXISTS carry_service_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed shipping rates (common routes) - ignore if already exists
INSERT IGNORE INTO shipping_rates (origin_country, destination_country, base_rate, rate_per_kg) VALUES
('CN', 'US', 5.00, 3.50),
('CN', 'GB', 5.50, 3.80),
('CN', 'BD', 3.00, 2.00),
('CN', 'DE', 5.50, 3.80),
('CN', 'JP', 4.00, 2.50),
('CN', 'AE', 4.50, 3.00),
('US', 'GB', 6.00, 4.00),
('US', 'CN', 5.00, 3.50),
('US', 'BD', 7.00, 5.00),
('BD', 'US', 7.00, 5.00),
('BD', 'GB', 6.50, 4.50),
('BD', 'CN', 3.00, 2.00),
('BD', 'AE', 4.00, 3.00);

-- Seed carry service settings - ignore if already exists
INSERT IGNORE INTO carry_service_settings (setting_key, setting_value, description) VALUES
('platform_commission_percent', '15', 'Platform commission on carry service (%)'),
('min_price_per_kg', '5', 'Minimum price per kg for carry service'),
('max_price_per_kg', '100', 'Maximum price per kg for carry service'),
('min_carrier_rating', '3.0', 'Minimum rating to remain active carrier'),
('max_weight_per_request', '30', 'Maximum weight per carry request (kg)'),
('auto_expire_trips_days', '1', 'Auto expire trips after travel date + N days'),
('min_payout_amount', '20', 'Minimum carrier payout amount ($)');
