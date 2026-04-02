-- =============================================================
-- GlobexSky Seed Data
-- Run AFTER schema.sql
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

USE `globexsky_db`;

-- =============================================================
-- LANGUAGES
-- =============================================================

INSERT INTO `languages` (`code`, `name`, `native_name`, `is_rtl`, `is_active`) VALUES
('en', 'English',            'English',    0, 1),
('bn', 'Bengali',            'বাংলা',       0, 1),
('ar', 'Arabic',             'العربية',     1, 1),
('hi', 'Hindi',              'हिन्दी',       0, 1),
('zh', 'Chinese Simplified', '中文',         0, 1),
('fr', 'French',             'Français',    0, 1),
('es', 'Spanish',            'Español',     0, 1);

-- =============================================================
-- CURRENCIES
-- =============================================================

INSERT INTO `currencies` (`code`, `name`, `symbol`, `exchange_rate`, `is_active`) VALUES
('USD', 'US Dollar',          '$',    1.000000, 1),
('BDT', 'Bangladeshi Taka',   '৳',   110.500000, 1),
('EUR', 'Euro',               '€',    0.921000, 1),
('GBP', 'British Pound',      '£',    0.787000, 1),
('CNY', 'Chinese Yuan',       '¥',    7.240000, 1),
('INR', 'Indian Rupee',       '₹',   83.120000, 1),
('AED', 'UAE Dirham',         'د.إ',  3.672000, 1);

-- =============================================================
-- ADMIN USER
-- Password: Admin@123  (password_hash with PASSWORD_BCRYPT, cost 12)
-- =============================================================

INSERT INTO `users`
    (`id`, `name`, `email`, `password`, `role`, `is_active`, `is_verified`, `created_at`)
VALUES
    (1, 'Super Admin', 'admin@globexsky.com',
     '$2y$12$EQoPsvtRKVFuI7MoV/Y8xuJgR3UlVHItT9CYuIsVX7GvTMuEZJv/K',
     'superadmin', 1, 1, NOW());

-- Note: password is Admin@123 (bcrypt cost 12)

INSERT INTO `user_profiles` (`user_id`, `company_name`, `country`, `city`) VALUES
    (1, 'GlobexSky Inc.', 'United States', 'New York');

-- Sample buyer account (Password: Buyer@123)
INSERT INTO `users`
    (`id`, `name`, `email`, `password`, `role`, `is_active`, `is_verified`, `created_at`)
VALUES
    (2, 'Demo Buyer', 'buyer@demo.com',
     '$2y$12$iA1Uvk1prM6JbGffLHib0uXc2F.yCKkUEr1.3vonrmtwW/BMR.0n6',
     'buyer', 1, 1, NOW());

INSERT INTO `user_profiles` (`user_id`, `country`, `city`) VALUES (2, 'United Kingdom', 'London');

-- Sample supplier account (Password: Supplier@123)
INSERT INTO `users`
    (`id`, `name`, `email`, `password`, `role`, `is_active`, `is_verified`, `created_at`)
VALUES
    (3, 'Demo Supplier', 'supplier@demo.com',
     '$2y$12$/OcmQG6.D43IQPus/3ADsO.fMFAiAvfYqGlZVoD6LeVo/BD64pVP6',
     'supplier', 1, 1, NOW());

INSERT INTO `user_profiles` (`user_id`, `company_name`, `country`, `city`) VALUES
    (3, 'Demo Global Trading Co.', 'China', 'Shenzhen');

-- =============================================================
-- SUPPLIER PLANS
-- =============================================================

INSERT INTO `supplier_plans`
    (`id`, `name`, `price_monthly`, `commission_rate`, `max_products`,
     `ai_marketing_budget`, `features`, `is_active`)
VALUES
(1, 'Basic', 0.00, 0.0800, 50, 0.00,
 JSON_OBJECT(
     'storefront', true,
     'rfq_responses', 5,
     'product_analytics', false,
     'priority_listing', false,
     'ai_marketing', false,
     'api_access', false,
     'live_streaming', false,
     'verified_badge', false,
     'bulk_upload', false
 ), 1),

(2, 'Professional', 49.00, 0.0500, 500, 50.00,
 JSON_OBJECT(
     'storefront', true,
     'rfq_responses', 50,
     'product_analytics', true,
     'priority_listing', true,
     'ai_marketing', true,
     'api_access', false,
     'live_streaming', true,
     'verified_badge', true,
     'bulk_upload', true,
     'dedicated_support', false
 ), 1),

(3, 'Enterprise', 199.00, 0.0300, 0,
 500.00,
 JSON_OBJECT(
     'storefront', true,
     'rfq_responses', -1,
     'product_analytics', true,
     'priority_listing', true,
     'ai_marketing', true,
     'api_access', true,
     'live_streaming', true,
     'verified_badge', true,
     'bulk_upload', true,
     'dedicated_support', true,
     'custom_domain', true,
     'white_label', true
 ), 1);

-- =============================================================
-- DEMO SUPPLIER RECORD
-- =============================================================

INSERT INTO `suppliers`
    (`id`, `user_id`, `business_name`, `business_type`, `country`,
     `description`, `rating`, `is_verified`, `verification_status`, `plan_id`, `plan_expires_at`)
VALUES
(1, 3, 'Demo Global Trading Co.', 'Manufacturer', 'China',
 'Leading manufacturer of electronics and consumer goods based in Shenzhen, China.',
 4.50, 1, 'approved', 2, DATE_ADD(NOW(), INTERVAL 1 YEAR));

INSERT INTO `supplier_scorecard`
    (`supplier_id`, `on_time_delivery`, `response_rate`, `dispute_rate`, `quality_score`, `overall_score`)
VALUES
    (1, 97.50, 98.20, 0.80, 4.50, 4.60);

-- =============================================================
-- CATEGORIES (Top-level + sub-categories)
-- =============================================================

INSERT INTO `categories`
    (`id`, `name`, `slug`, `parent_id`, `description`, `icon`, `is_active`, `sort_order`)
VALUES
-- Root categories
(1,  'Electronics',        'electronics',        NULL, 'Consumer electronics, gadgets and tech accessories', 'fa fa-microchip',        1, 1),
(2,  'Fashion & Apparel',  'fashion-apparel',    NULL, 'Clothing, footwear and fashion accessories',          'fa fa-tshirt',           1, 2),
(3,  'Home & Garden',      'home-garden',        NULL, 'Furniture, decor, gardening and household items',     'fa fa-home',             1, 3),
(4,  'Sports & Outdoors',  'sports-outdoors',    NULL, 'Sports equipment, fitness gear and outdoor products', 'fa fa-football-ball',    1, 4),
(5,  'Beauty & Health',    'beauty-health',      NULL, 'Cosmetics, skincare, health supplements',             'fa fa-spa',              1, 5),
(6,  'Automotive',         'automotive',         NULL, 'Auto parts, accessories and tools',                   'fa fa-car',              1, 6),
(7,  'Food & Beverages',   'food-beverages',     NULL, 'Fresh produce, packaged food and beverages',          'fa fa-utensils',         1, 7),
(8,  'Industrial',         'industrial',         NULL, 'Machinery, tools and industrial supplies',            'fa fa-industry',         1, 8),
(9,  'Toys & Baby',        'toys-baby',          NULL, 'Toys, games and baby products',                       'fa fa-baby',             1, 9),
(10, 'Office Supplies',    'office-supplies',    NULL, 'Stationery, office furniture and equipment',          'fa fa-briefcase',        1, 10),

-- Electronics sub-categories
(11, 'Mobile & Smartphones',    'mobile-smartphones',    1, NULL, 'fa fa-mobile-alt',    1, 1),
(12, 'Computers & Laptops',     'computers-laptops',     1, NULL, 'fa fa-laptop',        1, 2),
(13, 'Audio & Headphones',      'audio-headphones',      1, NULL, 'fa fa-headphones',    1, 3),
(14, 'Cameras & Photography',   'cameras-photography',   1, NULL, 'fa fa-camera',        1, 4),
(15, 'Smart Home & IoT',        'smart-home-iot',        1, NULL, 'fa fa-wifi',          1, 5),

-- Fashion sub-categories
(16, "Men's Clothing",          'mens-clothing',         2, NULL, 'fa fa-male',          1, 1),
(17, "Women's Clothing",        'womens-clothing',       2, NULL, 'fa fa-female',        1, 2),
(18, 'Shoes & Footwear',        'shoes-footwear',        2, NULL, 'fa fa-shoe-prints',   1, 3),
(19, 'Bags & Luggage',          'bags-luggage',          2, NULL, 'fa fa-suitcase',      1, 4),

-- Home & Garden sub-categories
(20, 'Furniture',               'furniture',             3, NULL, 'fa fa-couch',         1, 1),
(21, 'Kitchen & Dining',        'kitchen-dining',        3, NULL, 'fa fa-blender',       1, 2),
(22, 'Bedding & Bath',          'bedding-bath',          3, NULL, 'fa fa-bed',           1, 3),
(23, 'Garden & Outdoor',        'garden-outdoor',        3, NULL, 'fa fa-seedling',      1, 4);

-- =============================================================
-- API PLANS
-- =============================================================

INSERT INTO `api_plans` (`id`, `name`, `requests_per_month`, `price_monthly`, `features`, `is_active`) VALUES
(1, 'Free',       1000,    0.00,  JSON_OBJECT('products_api', true, 'orders_api', false, 'webhook', false, 'sandbox', true), 1),
(2, 'Starter',    50000,   29.00, JSON_OBJECT('products_api', true, 'orders_api', true, 'webhook', false, 'sandbox', true), 1),
(3, 'Growth',     500000,  99.00, JSON_OBJECT('products_api', true, 'orders_api', true, 'webhook', true, 'sandbox', true, 'ai_endpoints', true), 1),
(4, 'Enterprise', 0,       299.00,JSON_OBJECT('products_api', true, 'orders_api', true, 'webhook', true, 'sandbox', true, 'ai_endpoints', true, 'sla_99_9', true, 'dedicated_support', true), 1);

-- =============================================================
-- LOYALTY TIERS
-- =============================================================

INSERT INTO `loyalty_tiers` (`id`, `name`, `min_points`, `discount_rate`, `benefits`, `badge_image`) VALUES
(1, 'Bronze',   0,     0.0000, JSON_OBJECT('label', 'Bronze Member',    'free_shipping_threshold', 100, 'early_access', false), NULL),
(2, 'Silver',   1000,  0.0200, JSON_OBJECT('label', 'Silver Member',    'free_shipping_threshold', 50,  'early_access', true), NULL),
(3, 'Gold',     5000,  0.0500, JSON_OBJECT('label', 'Gold Member',      'free_shipping_threshold', 0,   'early_access', true,  'dedicated_manager', false), NULL),
(4, 'Platinum', 20000, 0.0800, JSON_OBJECT('label', 'Platinum Member',  'free_shipping_threshold', 0,   'early_access', true,  'dedicated_manager', true, 'vip_sourcing', true), NULL);

-- =============================================================
-- COMMISSION TIERS
-- =============================================================

INSERT INTO `commission_tiers`
    (`id`, `min_order_value`, `max_order_value`, `commission_rate`, `min_commission`, `max_commission`)
VALUES
(1,    0.00,    99.99, 0.0800,   0.50,   50.00),
(2,  100.00,   499.99, 0.0600,   6.00,  150.00),
(3,  500.00,  1999.99, 0.0500,  25.00,  400.00),
(4, 2000.00,  9999.99, 0.0400,  80.00, 1200.00),
(5, 10000.00, NULL,    0.0300, 300.00,  NULL);

-- =============================================================
-- PRICING RULES (Global commission defaults)
-- =============================================================

INSERT INTO `pricing_rules`
    (`name`, `type`, `rule_type`, `rate`, `fixed_amount`, `is_active`)
VALUES
('Global Platform Commission',  'commission', 'global',   0.0500, NULL,  1),
('Electronics Commission',      'commission', 'category', 0.0600, NULL,  1),
('Fashion Commission',          'commission', 'category', 0.0700, NULL,  1),
('Standard Inspection Fee',     'inspection', 'global',   NULL,   149.00, 1),
('Express Inspection Fee',      'inspection', 'global',   NULL,   249.00, 1),
('Carry Service Platform Fee',  'commission', 'global',   0.1000, NULL,  1);

UPDATE `pricing_rules` SET `category_id` = 1 WHERE `name` = 'Electronics Commission';
UPDATE `pricing_rules` SET `category_id` = 2 WHERE `name` = 'Fashion Commission';

-- =============================================================
-- DROPSHIP MARKUP RULES
-- =============================================================

INSERT INTO `dropship_markup_rules`
    (`name`, `type`, `min_price`, `max_price`, `markup_rate`, `min_profit`, `is_active`)
VALUES
('Global Default Markup',           'global',       NULL,    NULL,    0.3000, 2.00,  1),
('Budget Items Markup',             'price_range',  0.01,    9.99,    0.5000, 1.50,  1),
('Mid-Range Items Markup',          'price_range',  10.00,   49.99,   0.3500, 5.00,  1),
('Premium Items Markup',            'price_range',  50.00,   199.99,  0.2500, 15.00, 1),
('High-Value Items Markup',         'price_range',  200.00,  NULL,    0.2000, 40.00, 1);

-- =============================================================
-- SHIPPING ZONES & RATES
-- =============================================================

INSERT INTO `shipping_zones` (`id`, `name`, `countries`, `is_active`) VALUES
(1, 'Domestic USA',          JSON_ARRAY('US'),                           1),
(2, 'Europe',                JSON_ARRAY('GB','DE','FR','IT','ES','NL','BE','PL','SE','NO'), 1),
(3, 'Asia Pacific',          JSON_ARRAY('CN','JP','KR','IN','SG','TH','MY','ID','AU','NZ'), 1),
(4, 'Middle East & Africa',  JSON_ARRAY('AE','SA','EG','NG','KE','ZA','TR','IL'), 1),
(5, 'Rest of World',         JSON_ARRAY('BR','MX','CA','AR','CL','CO','ZA'), 1),
(6, 'Bangladesh',            JSON_ARRAY('BD'),                           1);

INSERT INTO `shipping_rates`
    (`zone_id`, `weight_from`, `weight_to`, `base_price`, `price_per_kg`, `service_type`, `is_active`)
VALUES
-- Zone 1: USA
(1, 0.000, 1.000, 5.99,  0.00,  'standard', 1),
(1, 0.000, 1.000, 9.99,  0.00,  'express',  1),
(1, 1.001, 5.000, 8.99,  1.50,  'standard', 1),
(1, 1.001, 5.000, 14.99, 2.00,  'express',  1),
(1, 5.001, 20.000,12.99, 1.20,  'standard', 1),
-- Zone 3: Asia Pacific
(3, 0.000, 0.500, 8.00,  0.00,  'economy',  1),
(3, 0.000, 0.500, 14.99, 0.00,  'standard', 1),
(3, 0.000, 0.500, 24.99, 0.00,  'express',  1),
(3, 0.501, 2.000, 12.00, 4.00,  'economy',  1),
(3, 0.501, 2.000, 18.99, 5.50,  'standard', 1),
(3, 2.001, 10.000,16.00, 3.50,  'economy',  1),
(3, 2.001, 10.000,24.00, 4.50,  'standard', 1),
-- Zone 2: Europe
(2, 0.000, 0.500, 12.00, 0.00,  'standard', 1),
(2, 0.000, 0.500, 22.00, 0.00,  'express',  1),
(2, 0.501, 5.000, 15.00, 5.00,  'standard', 1),
-- Zone 6: Bangladesh
(6, 0.000, 1.000, 3.50,  0.00,  'standard', 1),
(6, 1.001, 5.000, 4.99,  1.00,  'standard', 1);

-- =============================================================
-- CARRY PRODUCT CATALOG
-- =============================================================

INSERT INTO `carry_product_catalog`
    (`id`, `name`, `category`, `rate_per_kg`, `description`, `is_active`)
VALUES
(1,  'Documents & Papers',             'Documents',         2.50, 'Legal documents, certificates, passports and printed materials',   1),
(2,  'Clothes & Textiles',             'Fashion',           3.00, 'Clothing items, fabrics and textile goods',                        1),
(3,  'Electronics (Small)',            'Electronics',       5.00, 'Small electronics like phones, earbuds, and accessories',          1),
(4,  'Electronics (Large)',            'Electronics',       6.50, 'Laptops, tablets, cameras and larger devices',                     1),
(5,  'Cosmetics & Beauty Products',    'Beauty',            4.00, 'Makeup, skincare, perfumes (non-pressurized only)',                 1),
(6,  'Food & Snacks (Dry/Packaged)',   'Food',              3.50, 'Dry packaged food, spices, tea, chocolate',                        1),
(7,  'Medicine & Supplements',         'Health',            5.00, 'OTC medicines, vitamins, health supplements (customs-compliant)',  1),
(8,  'Jewelry & Accessories',          'Jewelry',           6.00, 'Rings, necklaces, watches and fashion accessories',                1),
(9,  'Books & Stationery',             'Books',             2.00, 'Books, notebooks, art supplies',                                   1),
(10, 'Toys & Baby Items',              'Toys',              3.50, 'Small toys, baby clothes, feeding accessories',                    1),
(11, 'Sports & Fitness (Small)',       'Sports',            4.00, 'Resistance bands, wristbands, small fitness accessories',          1),
(12, 'Handicrafts & Souvenirs',        'Crafts',            3.00, 'Handmade items, cultural gifts, souvenirs',                       1),
(13, 'Auto Parts (Small)',             'Automotive',        4.50, 'Small car accessories, phone mounts, fuses',                      1),
(14, 'Business Samples',               'Business',          5.50, 'Product samples for B2B purposes',                                 1),
(15, 'Dried Foods & Herbs',            'Food',              3.00, 'Dried herbs, spices, tea leaves, coffee beans',                    1);

-- =============================================================
-- FAQ ENTRIES
-- =============================================================

INSERT INTO `faq` (`question`, `answer`, `category`, `sort_order`, `is_active`) VALUES

-- Getting Started
('What is GlobexSky?',
 'GlobexSky is a global B2B marketplace connecting buyers, suppliers, and carriers across 190+ countries. We offer product sourcing, international shipping, dropshipping, quality inspection, and AI-powered tools to streamline global trade.',
 'getting-started', 1, 1),

('How do I create an account?',
 'Click the "Register" button in the top navigation. Choose your account type (Buyer, Supplier, or Carrier), fill in your details, verify your email, and you\'re ready to start trading.',
 'getting-started', 2, 1),

('Is GlobexSky free to use?',
 'Buyer accounts are always free. Suppliers can list products on our Basic plan (free, 8% commission). Paid supplier plans start at $49/month with lower commission rates and more features.',
 'getting-started', 3, 1),

('What countries does GlobexSky support?',
 'GlobexSky supports buyers and suppliers from 190+ countries. Our platform is available in 7 languages (English, Bengali, Arabic, Hindi, Chinese, French, Spanish) and accepts 7 major currencies.',
 'getting-started', 4, 1),

-- Buying
('How does buying on GlobexSky work?',
 'Browse or search for products, contact the supplier directly or place an order. Your payment is held in escrow until you confirm satisfactory delivery. Use our RFQ system to request custom quotes from multiple suppliers at once.',
 'buying', 1, 1),

('What payment methods are accepted?',
 'We accept Visa, Mastercard, American Express, PayPal, Stripe, bKash (Bangladesh), Nagad (Bangladesh), and bank transfers. All payments are processed securely.',
 'buying', 2, 1),

('What is Escrow payment protection?',
 'When you pay using GlobexSky Escrow, your funds are held securely by GlobexSky until you confirm receipt of the goods. If there is a dispute, our team mediates and protects both buyer and supplier.',
 'buying', 3, 1),

('How do I track my order?',
 'Go to "My Orders" in your account dashboard. Click on any order to view real-time tracking updates, courier information and estimated delivery date.',
 'buying', 4, 1),

('Can I return a product?',
 'Yes. If the product does not match the description or is damaged, open a dispute within 7 days of delivery. Our team will review and process a refund if the claim is valid.',
 'buying', 5, 1),

-- Suppliers
('How do I become a supplier on GlobexSky?',
 'Register with a Supplier account, complete your business profile, upload your business registration documents for verification, and list your products. Verified suppliers receive a trust badge and priority listing.',
 'suppliers', 1, 1),

('What are the supplier commission rates?',
 'Commission rates depend on your plan: Basic (8%), Professional (5%), Enterprise (3%). Commissions are calculated on the total order value and deducted from your payout.',
 'suppliers', 2, 1),

('How and when do suppliers get paid?',
 'Supplier payouts are processed within 3-5 business days after successful order delivery confirmation. You can withdraw via bank transfer, PayPal, bKash, or Nagad from your supplier dashboard.',
 'suppliers', 3, 1),

-- Carry Service
('What is the Carry Service?',
 'The Carry Service connects travelers (carriers) who have spare luggage allowance with buyers who need items transported between countries. Carriers earn money by carrying packages on their flights.',
 'carry', 1, 1),

('How do I become a carrier?',
 'Register as a Carrier, complete identity verification (passport + photo ID + facial recognition), post your upcoming trip with available weight, and start accepting delivery requests.',
 'carry', 2, 1),

('How are carriers verified?',
 'All carriers undergo a strict verification process including passport verification, government ID check, facial recognition matching, and background screening. Only approved carriers can accept carry requests.',
 'carry', 3, 1),

('How does delivery confirmation work for Carry?',
 'Each Carry delivery has a unique QR code. The buyer scans the carrier\'s QR code at handoff to confirm receipt. This triggers payment release and updates the delivery status.',
 'carry', 4, 1),

-- Shipping
('How does standard parcel shipping work?',
 'Enter your parcel details, choose a service (Economy, Standard, Express), pay online, print your label, drop off at a partner location or schedule a pickup. Track your parcel in real time from pickup to delivery.',
 'shipping', 1, 1),

('What items are prohibited from shipping?',
 'Prohibited items include: hazardous materials, flammable liquids, firearms and ammunition, counterfeit goods, illegal narcotics, and items restricted by destination country customs regulations.',
 'shipping', 2, 1),

-- Dropshipping
('What is the GlobexSky Dropshipping service?',
 'Our dropshipping service lets you sell products from Alibaba, 1688, and AliExpress without holding inventory. Set your own markup rules, take orders on your storefront, and we handle supplier ordering and shipping to your customers.',
 'dropshipping', 1, 1),

('How do markup rules work?',
 'You can set global markup percentages or category-specific rules. For example, set 30% markup on all products or 50% on items under $10. Prices are automatically calculated when products are imported.',
 'dropshipping', 2, 1),

-- API
('How do I get API access?',
 'Register an API client from your account dashboard, choose an API plan, and generate your API keys. Our free plan includes 1,000 requests/month. Full documentation is available at our API Portal.',
 'api', 1, 1),

-- Security
('How does GlobexSky protect my data?',
 'We use AES-256 encryption for data at rest, TLS 1.3 for data in transit, PCI DSS-compliant payment processing, GDPR-compliant data handling, and two-factor authentication (2FA) for all accounts.',
 'security', 1, 1),

('How do I enable Two-Factor Authentication?',
 'Go to Account Settings → Security → Enable Two-Factor Authentication. Download an authenticator app (Google Authenticator, Authy), scan the QR code, and enter the verification code to activate 2FA.',
 'security', 2, 1);

-- =============================================================
-- PLATFORM SETTINGS
-- =============================================================

INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `description`) VALUES
('site_name',                  'GlobexSky',                        'Platform display name'),
('site_tagline',               'Global B2B Marketplace & Logistics', 'Site tagline / headline'),
('site_url',                   'https://globexsky.com',             'Primary site URL'),
('admin_email',                'admin@globexsky.com',               'Admin notification email'),
('support_email',              'support@globexsky.com',             'Customer support email'),
('noreply_email',              'noreply@globexsky.com',             'System email sender address'),
('default_currency',           'USD',                               'Default currency code'),
('default_language',           'en',                                'Default site language code'),
('default_timezone',           'UTC',                               'Platform timezone'),
('platform_commission_rate',   '0.05',                              'Default platform commission (fraction)'),
('escrow_release_days',        '7',                                 'Days after delivery to auto-release escrow'),
('max_upload_size_mb',         '10',                                'Maximum file upload size in MB'),
('allowed_image_types',        'jpg,jpeg,png,webp,gif',             'Allowed image MIME extensions'),
('max_product_images',         '10',                                'Maximum images per product listing'),
('smtp_host',                  '',                                  'SMTP server host'),
('smtp_port',                  '587',                               'SMTP server port'),
('smtp_encryption',            'tls',                               'SMTP encryption (tls/ssl)'),
('smtp_username',              '',                                  'SMTP username'),
('stripe_public_key',          '',                                  'Stripe publishable key'),
('paypal_client_id',           '',                                  'PayPal client ID'),
('bkash_app_key',              '',                                  'bKash API app key'),
('google_analytics_id',        '',                                  'Google Analytics measurement ID'),
('google_maps_api_key',        '',                                  'Google Maps API key'),
('recaptcha_site_key',         '',                                  'reCAPTCHA v3 site key'),
('deepseek_model',             'deepseek-chat',                     'DeepSeek model for AI assistant'),
('ai_max_tokens',              '2000',                              'Max tokens for AI responses'),
('carry_platform_fee_rate',    '0.10',                              'Carry service platform fee (fraction)'),
('inspection_base_price',      '149.00',                            'Standard inspection base price USD'),
('inspection_rush_fee',        '100.00',                            'Rush inspection surcharge USD'),
('loyalty_points_per_dollar',  '10',                                'Loyalty points earned per $1 spent'),
('loyalty_redemption_rate',    '0.01',                              'USD value per loyalty point'),
('rfq_expiry_days',            '30',                                'Days before RFQ auto-closes'),
('max_rfq_quotes',             '10',                                'Maximum quotes per RFQ'),
('session_lifetime_seconds',   '7200',                              'User session lifetime in seconds'),
('remember_me_days',           '30',                                'Remember-me cookie lifetime in days'),
('email_verification_required','1',                                 '1 = require email verification on register'),
('min_withdrawal_usd',         '20.00',                             'Minimum payout withdrawal amount in USD'),
('maintenance_mode',           '0',                                 '1 = site in maintenance mode'),
('registration_open',          '1',                                 '1 = allow new user registrations'),
('supplier_auto_approve',      '0',                                 '1 = auto-approve supplier listings'),
('product_auto_approve',       '0',                                 '1 = auto-approve product listings'),
('review_auto_approve',        '0',                                 '1 = auto-approve customer reviews'),
('newsletter_enabled',         '1',                                 '1 = newsletter subscription enabled'),
('live_chat_enabled',          '1',                                 '1 = AI chat widget enabled'),
('trade_show_registration_fee','0.00',                              'Virtual booth registration fee USD');

-- =============================================================
-- FEATURE TOGGLES
-- =============================================================

INSERT INTO `feature_toggles` (`feature_key`, `feature_name`, `description`, `is_enabled`) VALUES
('product_sourcing',       'Product Sourcing',          'B2B product marketplace with supplier listings',       1),
('rfq_system',             'RFQ System',                'Request for Quote system for bulk buyers',             1),
('carry_service',          'Carry Service',             'Personal courier delivery via travelers',              1),
('parcel_shipping',        'Parcel Shipping',           'Standard international parcel shipping service',       1),
('dropshipping',           'Dropshipping',              'Dropshipping from Alibaba/1688/AliExpress',            1),
('quality_inspection',     'Quality Inspection',        'Third-party product quality inspection service',       1),
('escrow_payments',        'Escrow Payments',           'Secure escrow-based payment protection',               1),
('live_streaming',         'Live Streaming',            'Supplier product live stream sales',                   1),
('trade_shows',            'Virtual Trade Shows',       'Virtual trade show booths for suppliers',              1),
('ai_assistant',           'AI Assistant',              'DeepSeek-powered AI chat assistant',                   1),
('ai_recommendations',     'AI Recommendations',        'AI-powered product recommendation engine',             1),
('ai_fraud_detection',     'AI Fraud Detection',        'Automated fraud risk scoring for orders',              1),
('api_platform',           'API Platform',              'REST API access for developers and integrations',      1),
('loyalty_program',        'Loyalty Program',           'Points-based loyalty rewards program',                 1),
('multi_currency',         'Multi-Currency',            'Automatic currency conversion at checkout',            1),
('multi_language',         'Multi-Language',            'Platform UI in multiple languages',                    1),
('two_factor_auth',        'Two-Factor Auth',           '2FA via TOTP authenticator apps',                      1),
('social_login',           'Social Login',              'OAuth login via Google, Facebook, LinkedIn',           0),
('supplier_analytics',     'Supplier Analytics',        'Sales analytics dashboard for suppliers',              1),
('bulk_product_upload',    'Bulk Product Upload',       'CSV/Excel bulk product listing upload',                1),
('product_certifications', 'Product Certifications',    'Upload and display product certification docs',        1),
('review_system',          'Review System',             'Buyer product reviews and ratings',                    1),
('referral_program',       'Referral Program',          'User referral bonuses and tracking',                   0),
('newsletter',             'Newsletter',                'Email newsletter subscription system',                 1),
('support_tickets',        'Support Tickets',           'Customer support ticketing system',                    1),
('order_disputes',         'Order Disputes',            'Buyer/supplier order dispute resolution',              1),
('advanced_search',        'Advanced Search',           'Faceted search with filters and sorting',              1),
('compare_products',       'Product Compare',           'Side-by-side product comparison tool',                 1),
('wishlist',               'Wishlist',                  'Save products to personal wishlist',                   1),
('flash_sales',            'Flash Sales',               'Time-limited discount campaigns',                      1),
('coupon_system',          'Coupon System',             'Discount coupon codes at checkout',                    1),
('advertisements',         'Advertisements',            'Paid featured listings and banner ads',                1),
('cargo_insurance',        'Cargo Insurance',           'Optional shipping insurance for parcels',              1),
('trade_finance',          'Trade Finance',             'Letters of credit and financing options',              0);

-- =============================================================
-- ADMIN ROLES
-- =============================================================

INSERT INTO `admin_roles` (`id`, `name`, `permissions`) VALUES
(1, 'Super Admin', JSON_OBJECT(
    'users',        JSON_ARRAY('view','create','edit','delete','impersonate'),
    'products',     JSON_ARRAY('view','create','edit','delete','approve'),
    'orders',       JSON_ARRAY('view','edit','refund','cancel'),
    'suppliers',    JSON_ARRAY('view','verify','suspend','delete'),
    'payments',     JSON_ARRAY('view','refund','payout'),
    'settings',     JSON_ARRAY('view','edit'),
    'reports',      JSON_ARRAY('view','export'),
    'support',      JSON_ARRAY('view','reply','close'),
    'cms',          JSON_ARRAY('view','create','edit','delete'),
    'ai',           JSON_ARRAY('view','configure')
)),
(2, 'Admin', JSON_OBJECT(
    'users',     JSON_ARRAY('view','edit'),
    'products',  JSON_ARRAY('view','edit','approve'),
    'orders',    JSON_ARRAY('view','edit'),
    'suppliers', JSON_ARRAY('view','verify'),
    'payments',  JSON_ARRAY('view'),
    'settings',  JSON_ARRAY('view'),
    'reports',   JSON_ARRAY('view'),
    'support',   JSON_ARRAY('view','reply','close'),
    'cms',       JSON_ARRAY('view','create','edit')
)),
(3, 'Support Agent', JSON_OBJECT(
    'users',   JSON_ARRAY('view'),
    'orders',  JSON_ARRAY('view'),
    'support', JSON_ARRAY('view','reply','close')
)),
(4, 'Content Manager', JSON_OBJECT(
    'cms',      JSON_ARRAY('view','create','edit','delete'),
    'products', JSON_ARRAY('view','edit'),
    'banners',  JSON_ARRAY('view','create','edit','delete')
));

-- =============================================================
-- EMAIL TEMPLATES
-- =============================================================

INSERT INTO `email_templates` (`name`, `subject`, `body`, `variables`, `is_active`) VALUES

('welcome_email',
 'Welcome to GlobexSky — Your Global Trade Journey Starts Now!',
 '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:20px">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden">
  <div style="background:linear-gradient(135deg,#0d6efd,#00d4ff);padding:30px;text-align:center">
    <h1 style="color:#fff;margin:0">Welcome to GlobexSky!</h1>
  </div>
  <div style="padding:30px">
    <p>Hi {{name}},</p>
    <p>Welcome to GlobexSky — the global B2B marketplace built for modern trade.</p>
    <p>Your account is now active. Here is what you can do next:</p>
    <ul>
      <li>Browse 10M+ products from verified suppliers</li>
      <li>Post RFQs to get competitive quotes</li>
      <li>Ship parcels to 190+ countries</li>
      <li>Use our AI assistant for trade guidance</li>
    </ul>
    <div style="text-align:center;margin:30px 0">
      <a href="{{login_url}}" style="background:linear-gradient(135deg,#0d6efd,#00d4ff);color:#fff;padding:12px 30px;border-radius:6px;text-decoration:none;font-weight:bold">
        Get Started
      </a>
    </div>
    <p style="color:#666;font-size:12px">If you did not create this account, please ignore this email.</p>
  </div>
  <div style="background:#f8f9fa;padding:15px;text-align:center;color:#999;font-size:12px">
    &copy; {{year}} GlobexSky Inc. | <a href="{{unsubscribe_url}}" style="color:#999">Unsubscribe</a>
  </div>
</div>
</body></html>',
 JSON_OBJECT('name', 'User full name', 'login_url', 'Login URL', 'year', 'Current year', 'unsubscribe_url', 'Unsubscribe URL'),
 1),

('email_verification',
 'Verify Your GlobexSky Email Address',
 '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:20px">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden">
  <div style="background:linear-gradient(135deg,#0d6efd,#00d4ff);padding:30px;text-align:center">
    <h1 style="color:#fff;margin:0">Verify Your Email</h1>
  </div>
  <div style="padding:30px">
    <p>Hi {{name}},</p>
    <p>Please verify your email address to activate your GlobexSky account.</p>
    <div style="text-align:center;margin:30px 0">
      <a href="{{verification_url}}" style="background:linear-gradient(135deg,#0d6efd,#00d4ff);color:#fff;padding:14px 35px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:16px">
        Verify Email Address
      </a>
    </div>
    <p>Or copy this link into your browser:<br><a href="{{verification_url}}">{{verification_url}}</a></p>
    <p style="color:#666">This link expires in 24 hours. If you did not register, please ignore this email.</p>
  </div>
</div>
</body></html>',
 JSON_OBJECT('name', 'User full name', 'verification_url', 'Email verification URL'),
 1),

('password_reset',
 'Reset Your GlobexSky Password',
 '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:20px">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px">
  <div style="background:linear-gradient(135deg,#0d6efd,#00d4ff);padding:30px;text-align:center">
    <h1 style="color:#fff;margin:0">Password Reset</h1>
  </div>
  <div style="padding:30px">
    <p>Hi {{name}},</p>
    <p>We received a request to reset the password for your account associated with this email.</p>
    <div style="text-align:center;margin:30px 0">
      <a href="{{reset_url}}" style="background:#dc3545;color:#fff;padding:14px 35px;border-radius:6px;text-decoration:none;font-weight:bold">
        Reset Password
      </a>
    </div>
    <p>This link expires in <strong>1 hour</strong>.</p>
    <p style="color:#666">If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>
  </div>
</div>
</body></html>',
 JSON_OBJECT('name', 'User full name', 'reset_url', 'Password reset URL'),
 1),

('order_confirmation',
 'Order Confirmed — #{{order_number}}',
 '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:20px">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px">
  <div style="background:linear-gradient(135deg,#0d6efd,#00d4ff);padding:30px;text-align:center">
    <h1 style="color:#fff;margin:0">Order Confirmed! ✓</h1>
  </div>
  <div style="padding:30px">
    <p>Hi {{buyer_name}},</p>
    <p>Your order <strong>#{{order_number}}</strong> has been confirmed and is being processed.</p>
    <table style="width:100%;border-collapse:collapse">
      <tr style="background:#f8f9fa">
        <td style="padding:10px">Order Number</td>
        <td style="padding:10px"><strong>{{order_number}}</strong></td>
      </tr>
      <tr>
        <td style="padding:10px">Order Total</td>
        <td style="padding:10px"><strong>{{order_total}}</strong></td>
      </tr>
      <tr style="background:#f8f9fa">
        <td style="padding:10px">Payment Status</td>
        <td style="padding:10px">{{payment_status}}</td>
      </tr>
      <tr>
        <td style="padding:10px">Estimated Delivery</td>
        <td style="padding:10px">{{estimated_delivery}}</td>
      </tr>
    </table>
    <div style="text-align:center;margin:25px 0">
      <a href="{{order_url}}" style="background:linear-gradient(135deg,#0d6efd,#00d4ff);color:#fff;padding:12px 30px;border-radius:6px;text-decoration:none">
        View Order Details
      </a>
    </div>
  </div>
</div>
</body></html>',
 JSON_OBJECT('buyer_name', 'Buyer name', 'order_number', 'Order number', 'order_total', 'Formatted total', 'payment_status', 'Payment status', 'estimated_delivery', 'Estimated delivery date', 'order_url', 'Order detail URL'),
 1),

('order_shipped',
 'Your Order #{{order_number}} Has Been Shipped!',
 '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:20px">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px">
  <div style="background:linear-gradient(135deg,#198754,#20c997);padding:30px;text-align:center">
    <h1 style="color:#fff;margin:0">Your Order is on Its Way! 🚚</h1>
  </div>
  <div style="padding:30px">
    <p>Hi {{buyer_name}},</p>
    <p>Great news! Your order <strong>#{{order_number}}</strong> has been shipped.</p>
    <p><strong>Tracking Number:</strong> {{tracking_number}}<br>
    <strong>Carrier:</strong> {{carrier_name}}</p>
    <div style="text-align:center;margin:25px 0">
      <a href="{{tracking_url}}" style="background:linear-gradient(135deg,#198754,#20c997);color:#fff;padding:12px 30px;border-radius:6px;text-decoration:none">
        Track Shipment
      </a>
    </div>
  </div>
</div>
</body></html>',
 JSON_OBJECT('buyer_name', 'Buyer name', 'order_number', 'Order number', 'tracking_number', 'Tracking number', 'carrier_name', 'Shipping carrier', 'tracking_url', 'Tracking URL'),
 1),

('new_rfq_quote',
 'New Quote Received for Your RFQ: {{rfq_title}}',
 '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:20px">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px">
  <div style="background:linear-gradient(135deg,#0d6efd,#00d4ff);padding:30px;text-align:center">
    <h1 style="color:#fff;margin:0">New Quote Received</h1>
  </div>
  <div style="padding:30px">
    <p>Hi {{buyer_name}},</p>
    <p>You have received a new quote for your RFQ: <strong>{{rfq_title}}</strong></p>
    <p><strong>Supplier:</strong> {{supplier_name}}<br>
    <strong>Quoted Price:</strong> {{quote_price}}<br>
    <strong>Lead Time:</strong> {{lead_time}}</p>
    <div style="text-align:center;margin:25px 0">
      <a href="{{rfq_url}}" style="background:linear-gradient(135deg,#0d6efd,#00d4ff);color:#fff;padding:12px 30px;border-radius:6px;text-decoration:none">
        View Quote
      </a>
    </div>
  </div>
</div>
</body></html>',
 JSON_OBJECT('buyer_name', 'Buyer name', 'rfq_title', 'RFQ title', 'supplier_name', 'Supplier name', 'quote_price', 'Quoted price', 'lead_time', 'Lead time', 'rfq_url', 'RFQ detail URL'),
 1),

('supplier_verified',
 'Congratulations — Your Supplier Account is Verified!',
 '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:20px">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px">
  <div style="background:linear-gradient(135deg,#198754,#20c997);padding:30px;text-align:center">
    <h1 style="color:#fff;margin:0">Account Verified! ✓</h1>
  </div>
  <div style="padding:30px">
    <p>Hi {{supplier_name}},</p>
    <p>Congratulations! Your GlobexSky supplier account has been successfully verified. You now have a Verified Supplier badge on your profile, giving buyers extra confidence to work with you.</p>
    <p>You can now access all features of your {{plan_name}} plan, including priority listing in search results.</p>
    <div style="text-align:center;margin:25px 0">
      <a href="{{dashboard_url}}" style="background:linear-gradient(135deg,#198754,#20c997);color:#fff;padding:12px 30px;border-radius:6px;text-decoration:none">
        Go to Dashboard
      </a>
    </div>
  </div>
</div>
</body></html>',
 JSON_OBJECT('supplier_name', 'Supplier business name', 'plan_name', 'Current plan name', 'dashboard_url', 'Supplier dashboard URL'),
 1),

('carry_delivery_matched',
 'Carry Delivery Request Matched — #{{delivery_id}}',
 '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:20px">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px">
  <div style="background:linear-gradient(135deg,#0d6efd,#00d4ff);padding:30px;text-align:center">
    <h1 style="color:#fff;margin:0">Delivery Matched! ✈️</h1>
  </div>
  <div style="padding:30px">
    <p>Hi {{carrier_name}},</p>
    <p>You have a new delivery match for your trip from <strong>{{origin}}</strong> to <strong>{{destination}}</strong>.</p>
    <p><strong>Item:</strong> {{item_name}}<br>
    <strong>Weight:</strong> {{weight}} kg<br>
    <strong>Your Earnings:</strong> {{earnings}}</p>
    <p>Please accept or decline this request within 12 hours.</p>
    <div style="text-align:center;margin:25px 0">
      <a href="{{delivery_url}}" style="background:linear-gradient(135deg,#0d6efd,#00d4ff);color:#fff;padding:12px 30px;border-radius:6px;text-decoration:none">
        View &amp; Respond
      </a>
    </div>
  </div>
</div>
</body></html>',
 JSON_OBJECT('carrier_name', 'Carrier name', 'origin', 'Origin country', 'destination', 'Destination country', 'item_name', 'Item being carried', 'weight', 'Item weight in kg', 'earnings', 'Carrier earnings', 'delivery_url', 'Delivery detail URL'),
 1),

('newsletter_confirmation',
 'You are now subscribed to GlobexSky Updates!',
 '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:20px">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px">
  <div style="background:linear-gradient(135deg,#0d6efd,#00d4ff);padding:30px;text-align:center">
    <h1 style="color:#fff;margin:0">Subscribed! 🎉</h1>
  </div>
  <div style="padding:30px">
    <p>Thank you for subscribing to GlobexSky trade insights!</p>
    <p>You will receive weekly updates on:</p>
    <ul>
      <li>New verified suppliers and trending products</li>
      <li>Exclusive deals and flash sales</li>
      <li>Global trade news and insights</li>
      <li>Platform feature announcements</li>
    </ul>
    <p style="color:#666;font-size:12px">
      <a href="{{unsubscribe_url}}">Unsubscribe</a> at any time.
    </p>
  </div>
</div>
</body></html>',
 JSON_OBJECT('unsubscribe_url', 'One-click unsubscribe URL'),
 1);

-- =============================================================
-- SAMPLE CAMPAIGN / COUPON (Welcome offer)
-- =============================================================

INSERT INTO `campaigns`
    (`name`, `type`, `description`, `start_date`, `end_date`,
     `discount_type`, `discount_value`, `min_order`, `max_discount`,
     `usage_limit`, `status`)
VALUES
('New User Welcome Offer', 'coupon',
 'First-order discount for new registered buyers.',
 DATE_SUB(NOW(), INTERVAL 1 DAY),
 DATE_ADD(NOW(), INTERVAL 1 YEAR),
 'percent', 10.00, 50.00, 25.00,
 NULL, 'active');

INSERT INTO `coupons`
    (`campaign_id`, `code`, `type`, `value`, `min_order`,
     `max_uses`, `is_active`, `expires_at`)
VALUES
(1, 'WELCOME10', 'percent', 10.00, 50.00, NULL, 1, DATE_ADD(NOW(), INTERVAL 1 YEAR));

-- =============================================================
-- ACTIVITY LOG: Seed marker
-- =============================================================

INSERT INTO `activity_logs` (`user_id`, `action`, `description`, `ip_address`)
VALUES (1, 'seed_data', 'Database seeded with initial data by setup script', '127.0.0.1');

SET FOREIGN_KEY_CHECKS = 1;

-- End of seed.sql
