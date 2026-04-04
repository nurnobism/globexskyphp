-- ============================================================
-- GlobexSky — Demo Seed Data
-- Populates the database with realistic sample data so the
-- homepage, product listing, and other pages look alive.
--
-- Run AFTER all schema files have been imported.
-- Safe to run multiple times — uses INSERT IGNORE.
--
-- Usage:
--   mysql -u <user> -p <database> < database/seed_demo_data.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. CATEGORIES (10 top-level categories)
-- ============================================================

INSERT IGNORE INTO `categories`
    (`id`, `parent_id`, `name`, `slug`, `description`, `image`, `sort_order`, `is_active`)
VALUES
(101, NULL, 'Electronics',       'electronics',       'Consumer electronics, components, and gadgets for B2B buyers worldwide.',             'assets/uploads/categories/electronics.jpg',    1, 1),
(102, NULL, 'Machinery',         'machinery',         'Industrial machinery, equipment, and manufacturing tools.',                           'assets/uploads/categories/machinery.jpg',       2, 1),
(103, NULL, 'Apparel & Fashion', 'apparel-fashion',   'Wholesale clothing, fashion accessories, and textile products.',                      'assets/uploads/categories/apparel-fashion.jpg', 3, 1),
(104, NULL, 'Home & Garden',     'home-garden',       'Home furnishings, décor, kitchenware, and garden products.',                          'assets/uploads/categories/home-garden.jpg',     4, 1),
(105, NULL, 'Food & Beverage',   'food-beverage',     'Packaged food, beverages, organic products, and wholesale grocery items.',            'assets/uploads/categories/food-beverage.jpg',   5, 1),
(106, NULL, 'Chemicals',         'chemicals',         'Industrial chemicals, cleaning agents, and specialty compounds.',                     'assets/uploads/categories/chemicals.jpg',       6, 1),
(107, NULL, 'Automotive',        'automotive',        'Auto parts, accessories, tools, and vehicle components for wholesale buyers.',        'assets/uploads/categories/automotive.jpg',      7, 1),
(108, NULL, 'Health & Beauty',   'health-beauty',     'Healthcare products, cosmetics, wellness supplements, and personal care items.',      'assets/uploads/categories/health-beauty.jpg',   8, 1),
(109, NULL, 'Sports & Outdoors', 'sports-outdoors',   'Sports equipment, outdoor gear, fitness accessories, and recreational products.',     'assets/uploads/categories/sports-outdoors.jpg', 9, 1),
(110, NULL, 'Industrial',        'industrial',        'Industrial supplies, safety equipment, tools, and infrastructure components.',        'assets/uploads/categories/industrial.jpg',      10, 1);

-- ============================================================
-- 2. USERS (1 admin + 3 suppliers + 2 buyers)
-- Password for all: Demo@2026
-- Hash: $2y$12$LJ3EqGhKFcBN.UhxYbKpxe6FSCdJTHQMK7FE9Xd8.YrHJvUPdXKCe
-- ============================================================

INSERT IGNORE INTO `users`
    (`id`, `email`, `password_hash`, `name`, `role`, `status`, `is_verified`, `is_active`, `created_at`)
VALUES
(101, 'admin@globexsky.com',      '$2y$12$LJ3EqGhKFcBN.UhxYbKpxe6FSCdJTHQMK7FE9Xd8.YrHJvUPdXKCe', 'Admin User',                  'admin',    'active', 1, 1, NOW()),
(102, 'supplier1@demo.com',       '$2y$12$LJ3EqGhKFcBN.UhxYbKpxe6FSCdJTHQMK7FE9Xd8.YrHJvUPdXKCe', 'TechVision Electronics Ltd',  'supplier', 'active', 1, 1, NOW()),
(103, 'supplier2@demo.com',       '$2y$12$LJ3EqGhKFcBN.UhxYbKpxe6FSCdJTHQMK7FE9Xd8.YrHJvUPdXKCe', 'Global Machinery Corp',       'supplier', 'active', 1, 1, NOW()),
(104, 'supplier3@demo.com',       '$2y$12$LJ3EqGhKFcBN.UhxYbKpxe6FSCdJTHQMK7FE9Xd8.YrHJvUPdXKCe', 'FashionHub International',    'supplier', 'active', 1, 1, NOW()),
(105, 'buyer1@demo.com',          '$2y$12$LJ3EqGhKFcBN.UhxYbKpxe6FSCdJTHQMK7FE9Xd8.YrHJvUPdXKCe', 'Ahmed Rahman',                'buyer',    'active', 1, 1, NOW()),
(106, 'buyer2@demo.com',          '$2y$12$LJ3EqGhKFcBN.UhxYbKpxe6FSCdJTHQMK7FE9Xd8.YrHJvUPdXKCe', 'Sarah Chen',                  'buyer',    'active', 1, 1, NOW());

-- ============================================================
-- 3. SUPPLIERS (linked to supplier users above)
-- ============================================================

INSERT IGNORE INTO `suppliers`
    (`id`, `user_id`, `company_name`, `slug`, `description`, `country`, `city`,
     `verified`, `rating`, `response_time`, `established_year`,
     `employee_count`, `annual_revenue`, `total_products`, `total_orders`)
VALUES
(101, 102, 'TechVision Electronics Ltd',
    'techvision-electronics-ltd',
    'TechVision Electronics is a leading manufacturer and exporter of consumer electronics, smart devices, and electronic components. With over 15 years of experience, we supply to 60+ countries with ISO 9001 certified quality standards.',
    'China', 'Shenzhen',
    1, 4.80, '< 4 hours', 2008, '500-999', '$10M - $50M USD', 10, 245),

(102, 103, 'Global Machinery Corp',
    'global-machinery-corp',
    'Global Machinery Corp specializes in industrial machinery, CNC equipment, and manufacturing automation solutions. CE and ISO certified, serving manufacturing industries in Europe, Asia, and the Americas for over 20 years.',
    'Germany', 'Munich',
    1, 4.65, '< 8 hours', 2003, '200-499', '$50M - $100M USD', 10, 180),

(103, 104, 'FashionHub International',
    'fashionhub-international',
    'FashionHub International is Bangladesh''s premier garment manufacturer offering custom-branded apparel, fast fashion, and sustainable clothing solutions. BSCI and OEKO-TEX certified factory with 800+ skilled workers.',
    'Bangladesh', 'Dhaka',
    1, 4.50, '< 12 hours', 2010, '500-999', '$5M - $10M USD', 10, 320);

-- ============================================================
-- 4. PRODUCTS (30 products across categories)
-- ============================================================

-- --- Electronics (Supplier 101) ---

INSERT IGNORE INTO `products`
    (`id`, `supplier_id`, `category_id`, `name`, `slug`, `short_desc`, `description`,
     `sku`, `price`, `compare_price`, `min_order_qty`, `stock_qty`, `unit`,
     `images`, `status`, `is_featured`, `view_count`, `rating`, `review_count`)
VALUES
(101, 101, 101,
    'Wireless Bluetooth Earbuds Pro',
    'wireless-bluetooth-earbuds-pro',
    'Premium TWS earbuds with ANC, 30h battery life, IPX5 waterproof, and touch controls.',
    'Our Wireless Bluetooth Earbuds Pro deliver premium audio quality with Active Noise Cancellation technology. Features include 30-hour total battery life, IPX5 waterproof rating, touch controls, and a compact charging case. Compatible with iOS and Android. Ideal for retail resellers and corporate gifting. MOQ 50 units. Custom branding available.',
    'TV-BT-001', 12.50, 18.00, 50, 5000, 'pair',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 1, 15200, 4.70, 312),

(102, 101, 101,
    'Smart LED Display Panel 55"',
    'smart-led-display-panel-55inch',
    '4K UHD Smart LED TV panel with HDR10, Android OS, and 2-year warranty.',
    'The Smart LED Display Panel 55" features 4K UHD resolution, HDR10 support, and built-in Android OS for seamless streaming. Thin bezel design suitable for retail, hospitality, and corporate environments. Supports HDMI, USB, and Wi-Fi connectivity. Bulk pricing available for orders of 10+ units.',
    'TV-LED-002', 285.00, 350.00, 10, 800, 'piece',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 1, 9800, 4.60, 145),

(103, 101, 101,
    'Industrial Tablet PC 10.1"',
    'industrial-tablet-pc-10inch',
    'Rugged Android tablet with IP65 rating, 8-core CPU, 4G LTE, and 8000mAh battery.',
    'Built for demanding industrial environments, this 10.1" tablet features an IP65-rated rugged chassis, 8-core 2.0GHz processor, 4GB RAM, 64GB storage, and 4G LTE connectivity. Ideal for warehouse management, field operations, and logistics tracking. Sunlight-readable display included.',
    'TV-TAB-003', 89.00, 120.00, 20, 1200, 'piece',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 6700, 4.50, 88),

(104, 101, 101,
    'Solar Power Bank 20000mAh',
    'solar-power-bank-20000mah',
    'Dual-solar-panel power bank with 20000mAh capacity, 3 USB outputs, and LED flashlight.',
    'High-capacity solar power bank with dual solar panels for emergency outdoor charging. 20000mAh lithium polymer battery, 3 USB-A outputs + 1 USB-C PD, LED emergency flashlight. Rugged, water-resistant design. Perfect for outdoor retail, travel accessories, and emergency preparedness markets.',
    'TV-SPB-004', 18.90, 25.00, 100, 8000, 'piece',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 4300, 4.40, 195),

(105, 101, 101,
    'Smart Security Camera System',
    'smart-security-camera-system',
    '8-channel NVR system with 4K cameras, AI detection, night vision, and cloud backup.',
    'Complete 8-channel IP security system featuring 4K resolution cameras with AI-powered person and vehicle detection. Includes NVR recorder, 2TB HDD, night vision up to 30m, two-way audio, and mobile app access. Suitable for SME security installations and wholesale to security integrators.',
    'TV-CAM-005', 245.00, 320.00, 5, 600, 'set',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 1, 12100, 4.80, 267),

-- --- Machinery (Supplier 102) ---

(106, 102, 102,
    'CNC Milling Machine 5-Axis',
    'cnc-milling-machine-5-axis',
    'High-precision 5-axis CNC milling center with 24000 RPM spindle, Siemens CNC control.',
    'Professional 5-axis CNC milling machine designed for precision metalworking. Features a 24000 RPM high-speed spindle, Siemens 828D CNC control system, automatic tool changer (24 tools), and 800x600x500mm work envelope. Suitable for aerospace, automotive, and mold-making industries.',
    'GM-CNC-001', 22500.00, 28000.00, 1, 50, 'piece',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 1, 8900, 4.90, 42),

(107, 102, 102,
    'Industrial Air Compressor 500L',
    'industrial-air-compressor-500l',
    '500-litre rotary screw air compressor, 37kW, 10 bar, low-noise cabinet design.',
    'Heavy-duty rotary screw air compressor with 500L receiver tank and 37kW energy-efficient motor. Delivers 10 bar working pressure with 6.2 m³/min flow rate. Quiet cabinet design (<68dB), integrated air dryer and filter, remote monitoring interface. CE certified.',
    'GM-AIR-002', 4800.00, 5800.00, 1, 120, 'piece',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 1, 5600, 4.70, 58),

(108, 102, 102,
    'Hydraulic Press Machine 100T',
    'hydraulic-press-machine-100t',
    '100-ton H-frame hydraulic press with programmable controller and safety guard.',
    '100-ton four-column hydraulic press suitable for stamping, forming, and deep drawing operations. Equipped with programmable PLC controller, adjustable stroke and speed, integrated safety guard, and hardened steel frame. Max working pressure 315 bar. Available in 63T / 100T / 200T configurations.',
    'GM-HPM-003', 8900.00, 11000.00, 1, 80, 'piece',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 4200, 4.60, 31),

(109, 102, 102,
    'Automatic Packaging Machine',
    'automatic-packaging-machine',
    'High-speed VFFS packaging machine, 60 bags/min, suitable for powder and granule products.',
    'Vertical Form-Fill-Seal (VFFS) automatic packaging machine capable of producing 60 bags per minute. Compatible with powders, granules, and small solid products. Adjustable bag size from 80–300mm width, touch-screen HMI, servo motor-driven, stainless-steel contact parts. Food-grade and pharmaceutical compliant.',
    'GM-PKG-004', 6500.00, 8200.00, 1, 65, 'piece',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 3800, 4.55, 27),

(110, 102, 102,
    'Industrial Welding Robot Arm',
    'industrial-welding-robot-arm',
    '6-axis welding robot with 10kg payload, 1440mm reach, and arc welding package.',
    'Compact 6-axis industrial welding robot with 10kg payload, 1440mm arm reach, and ±0.08mm repeatability. Includes integrated MIG/MAG arc welding torch, wire feeder, weld controller, and offline programming software. Suitable for automotive body shops, metal fabrication, and contract manufacturing.',
    'GM-ROB-005', 18500.00, 23000.00, 1, 30, 'piece',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 1, 7100, 4.75, 19),

-- --- Apparel & Fashion (Supplier 103) ---

(111, 103, 103,
    'Custom Printed Cotton T-Shirts',
    'custom-printed-cotton-t-shirts',
    '100% combed cotton T-shirts, 180GSM, available in 30+ colors, custom logo printing.',
    'Premium quality 100% combed ring-spun cotton T-shirts, 180GSM, available in 30+ standard colors. Custom screen printing, heat transfer, or embroidery available. Size range XS–4XL. Meets OEKO-TEX Standard 100. Minimum order 200 pcs per design/color. Lead time 25–35 days.',
    'FH-TSH-001', 2.80, 4.50, 200, 50000, 'piece',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 1, 18900, 4.60, 428),

(112, 103, 103,
    'Premium Denim Jeans Wholesale',
    'premium-denim-jeans-wholesale',
    'Men''s slim-fit denim jeans, 12oz stretch denim, OEM available, sizes 28–42.',
    'Wholesale men''s slim-fit denim jeans made from premium 12oz stretch denim. Features include 5-pocket design, YKK zipper, and heavy-duty rivets. OEM branding and custom washing effects available (stone wash, acid wash, distressed). Sizes 28–42. BSCI certified factory.',
    'FH-DNM-002', 7.50, 12.00, 100, 30000, 'pair',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 9200, 4.40, 186),

(113, 103, 103,
    'Sports Activewear Set',
    'sports-activewear-set',
    '2-piece women''s activewear set, 88% polyester / 12% spandex, moisture-wicking.',
    'High-performance 2-piece women''s activewear set (leggings + sports bra) made from 88% polyester / 12% spandex blend. Four-way stretch, moisture-wicking, UPF 50+. Available in 20 color options and sizes XS–3XL. Custom logo print and private label available from 100 sets.',
    'FH-ACT-003', 8.90, 14.00, 100, 25000, 'set',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 1, 11400, 4.65, 302),

(114, 103, 103,
    'Leather Business Briefcase',
    'leather-business-briefcase',
    'Genuine leather briefcase with laptop compartment (fits 15.6"), brass hardware, OEM.',
    'Premium genuine cowhide leather business briefcase with padded 15.6" laptop compartment, organizer section, and magnetic closure. Brass-finish hardware, detachable shoulder strap. Available in black, dark brown, and tan. OEM/ODM orders accepted from 50 pieces.',
    'FH-BAG-004', 35.00, 55.00, 50, 3000, 'piece',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 6800, 4.55, 94),

(115, 103, 103,
    'Winter Down Jacket Collection',
    'winter-down-jacket-collection',
    '90/10 duck down jacket, 600-fill power, windproof shell, packable, unisex.',
    'Premium packable unisex winter down jacket filled with 90% white duck down (600-fill power). Windproof and water-resistant nylon shell, YKK zippers, interior zip pockets. Compresses to palm-sized carry pouch. Available in 15 colors, sizes XS–4XL. Custom label and packaging available.',
    'FH-JKT-005', 22.50, 38.00, 50, 12000, 'piece',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 1, 14300, 4.70, 231),

-- --- Home & Garden (Supplier 101) ---

(116, 101, 104,
    'Smart WiFi LED Bulb Set',
    'smart-wifi-led-bulb-set',
    '4-pack E27 smart bulbs, 9W, 16M colors, voice-controlled, no hub required.',
    'Set of 4 E27 smart LED bulbs with built-in WiFi (no hub required). 9W, 800lm, 16 million color options, warm white to daylight tunable. Compatible with Amazon Alexa, Google Assistant, and Apple HomeKit. Controlled via free app. Wholesale packs of 4, 6, 12 bulbs available.',
    'TV-SLB-006', 14.50, 22.00, 50, 10000, 'set',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 1, 13600, 4.55, 389),

(117, 101, 104,
    'Stainless Steel Cookware Set 12pc',
    'stainless-steel-cookware-set-12pc',
    '12-piece tri-ply stainless steel cookware set, induction compatible, oven safe to 500°F.',
    'Professional 12-piece tri-ply stainless steel cookware set including 4 saucepans (1Q, 2Q, 3Q, 4Q), 8" and 10" skillets, 6Q stockpot, and lids. Induction compatible, dishwasher safe, oven safe to 260°C. Riveted ergonomic handles. Suitable for restaurant supply and household wholesale.',
    'TV-CKW-007', 48.00, 72.00, 20, 4000, 'set',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 7800, 4.45, 163),

(118, 101, 104,
    'Garden Solar Path Lights',
    'garden-solar-path-lights',
    '8-pack stainless steel solar LED path lights, 200 lumens, auto on/off, IP65.',
    'Pack of 8 premium stainless steel solar-powered garden path lights. 200 lumens per light, auto on/off dusk-to-dawn sensor, 8-hour run time after full charge. IP65 waterproof, easy stake installation, no wiring required. Ideal for landscaping retailers and garden centers.',
    'TV-SPL-008', 19.80, 28.00, 30, 7500, 'pack',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 5400, 4.35, 217),

(119, 101, 104,
    'Memory Foam Mattress Queen',
    'memory-foam-mattress-queen',
    '12" queen size memory foam mattress, CertiPUR-US certified, medium-firm, rolled packaging.',
    'Premium 12" queen size memory foam mattress with 3-layer construction: 2" cooling gel memory foam + 2" comfort transition foam + 8" high-density base foam. CertiPUR-US certified. Roll-packed for easy shipping. 100-night trial offer for resellers. Medium-firm comfort level.',
    'TV-MAT-009', 185.00, 260.00, 5, 500, 'piece',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 8900, 4.75, 284),

(120, 101, 104,
    'Bamboo Storage Organizer Set',
    'bamboo-storage-organizer-set',
    '5-piece bamboo desktop organizer set — pen holder, tray, document rack, drawer.',
    '5-piece bamboo desk organizer set with pen/pencil cup, business card holder, paper tray, drawer unit, and document rack. Made from sustainably harvested moso bamboo. Natural finish, smooth edges. Perfect for office supply wholesalers and eco-friendly retail brands.',
    'TV-BBO-010', 11.50, 18.00, 100, 6000, 'set',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 4700, 4.30, 128),

-- --- Food & Beverage (Supplier 103) ---

(121, 103, 105,
    'Organic Green Tea Premium',
    'organic-green-tea-premium',
    'USDA organic green tea, first-flush Darjeeling, 100-bag box, individually wrapped.',
    'Premium first-flush Darjeeling organic green tea. USDA certified organic, non-GMO. 100 individually wrapped tea bags per box. Rich in antioxidants with a delicate floral aroma. Suitable for health food stores, cafes, and hotel/restaurant chains. Bulk carton pricing available from 50 boxes.',
    'FH-GT-001', 8.50, 13.00, 50, 20000, 'box',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 6200, 4.55, 197),

(122, 103, 105,
    'Instant Coffee Mix Wholesale',
    'instant-coffee-mix-wholesale',
    '3-in-1 instant coffee sachets, 25g each, 100 sachets per box, Arabica blend.',
    'Premium 3-in-1 instant coffee sachets made from 100% Arabica coffee blend with creamer and sugar. Each sachet 25g. 100 sachets per display box. Available in Original, Sugar-Free, and Extra Strong variants. OEM packaging from 500 boxes. Shelf life 24 months.',
    'FH-COF-002', 12.00, 18.00, 100, 50000, 'box',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 5800, 4.40, 143),

(123, 103, 105,
    'Dried Mixed Fruits Pack',
    'dried-mixed-fruits-pack',
    'Premium dried fruit mix: mango, pineapple, cranberry, raisin — 500g resealable bag.',
    'Premium tropical dried mixed fruits including mango slices, pineapple chunks, cranberries, and sultana raisins. 500g resealable stand-up pouch. No added sulfites or artificial colors. 12-month shelf life. Suitable for health food retailers, airport retail, and fitness snack brands.',
    'FH-FRT-003', 4.80, 7.50, 100, 30000, 'bag',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 3900, 4.35, 89),

(124, 103, 105,
    'Coconut Oil Virgin Cold Press',
    'coconut-oil-virgin-cold-press',
    'Unrefined virgin coconut oil, cold-pressed, 500ml glass bottle, USDA organic.',
    'Extra virgin coconut oil extracted via cold-press method from fresh organic coconuts. USDA organic certified. Unrefined, unbleached, no hexane or chemicals. 500ml glass bottles with tamper-evident seal. Suitable for food retail, cosmetic formulation, and health food distribution. 12 bottles per carton.',
    'FH-COO-004', 6.20, 9.50, 50, 15000, 'bottle',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 4500, 4.60, 156),

(125, 103, 105,
    'Protein Bar Variety Box',
    'protein-bar-variety-box',
    '24-bar variety box, 20g protein per bar, 6 flavors, whey + plant protein blend.',
    '24-piece protein bar variety box featuring 6 flavors (Chocolate Peanut Butter, Vanilla Almond, Dark Chocolate Berry, Caramel Sea Salt, Coconut Lime, Mixed Berry). 20g protein per bar, <5g sugar, 250 kcal. Whey + plant protein blend. Suitable for gyms, sports nutrition retailers, and corporate wellness programs.',
    'FH-PRO-005', 28.50, 38.00, 20, 10000, 'box',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 5100, 4.50, 201),

-- --- Health & Beauty (Supplier 102) ---

(126, 102, 108,
    'Vitamin C Serum Professional',
    'vitamin-c-serum-professional',
    '20% Vitamin C serum with hyaluronic acid and vitamin E, 30ml amber bottle, dermatologist tested.',
    'Professional-grade 20% L-Ascorbic Acid vitamin C serum formulated with Hyaluronic Acid and Vitamin E for maximum antioxidant protection. Brightens skin tone, reduces dark spots, and boosts collagen production. Dermatologist tested, paraben-free, vegan. 30ml amber glass bottle with dropper. Private label available from 500 units.',
    'GM-VCS-001', 6.50, 12.00, 100, 20000, 'bottle',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 1, 17800, 4.85, 476),

(127, 102, 108,
    'Natural Shampoo Set',
    'natural-shampoo-set',
    'Sulfate-free shampoo + conditioner set, argan oil formula, 400ml each, salon quality.',
    'Professional salon-quality natural shampoo and conditioner set with Moroccan argan oil. Sulfate-free, paraben-free, silicone-free formula. 400ml each. Suitable for all hair types. Moisturizes, repairs damage, and reduces frizz. Cruelty-free and vegan. Private label from 200 sets.',
    'GM-SHP-002', 9.80, 16.00, 100, 15000, 'set',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 9400, 4.60, 248),

(128, 102, 108,
    'Electric Massage Gun Pro',
    'electric-massage-gun-pro',
    'Percussion massager with 6 heads, 30 speed levels, 2500mAh, carrying case included.',
    'Professional percussion massage gun with 30 adjustable speed levels (1200–3200 RPM), 6 interchangeable massage heads, and 2500mAh rechargeable battery (6h runtime). Brushless motor with <45dB quiet operation. Carrying case included. Suitable for gyms, physiotherapy clinics, and sports retailers.',
    'GM-MSG-003', 32.00, 48.00, 30, 5000, 'piece',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 1, 14200, 4.75, 334),

(129, 102, 108,
    'Face Mask N95 Medical Grade',
    'face-mask-n95-medical-grade',
    'NIOSH-approved N95 respirator mask, 5-layer filtration, box of 20, individually wrapped.',
    'NIOSH-approved N95 respirator with 5-layer filtration (≥95% filtration of 0.3-micron particles). Adjustable nose wire, head straps, and foam nose cushion for comfortable extended wear. Individually wrapped in sterile packs. 20 masks per box. Suitable for healthcare procurement, industrial safety, and emergency stockpile.',
    'GM-MSK-004', 14.50, 20.00, 50, 100000, 'box',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 0, 11600, 4.70, 389),

(130, 102, 108,
    'Essential Oil Diffuser Set',
    'essential-oil-diffuser-set',
    '500ml ultrasonic diffuser + 10 pure essential oils set, 7-color LED, auto shut-off.',
    '500ml ultrasonic cool-mist essential oil diffuser with 7-color LED mood lighting, 4 timer settings, and auto shut-off. Includes 10x 10ml pure essential oils (lavender, peppermint, eucalyptus, tea tree, lemon, rosemary, bergamot, frankincense, ylang-ylang, orange). BPA-free, whisper-quiet. Gift-ready packaging.',
    'GM-EOD-005', 24.80, 38.00, 30, 8000, 'set',
    '["assets/uploads/products/placeholder.jpg"]',
    'active', 1, 16500, 4.80, 412);

-- ============================================================
-- 5. REVIEWS (20 sample reviews)
-- ============================================================

INSERT IGNORE INTO `reviews`
    (`id`, `product_id`, `user_id`, `rating`, `title`, `body`, `status`, `helpful`, `created_at`)
VALUES
(101, 101, 105, 5, 'Excellent quality earbuds!',
    'Ordered 200 units for our retail chain. Sound quality is outstanding and ANC works perfectly. Packaging is premium and customers love them. Will reorder.',
    'approved', 24, NOW()),

(102, 101, 106, 4, 'Good product, minor delay',
    'Quality is very good. Minor shipping delay but supplier communicated proactively. 4 stars for the slight delay, otherwise would be 5.',
    'approved', 12, NOW()),

(103, 102, 105, 5, 'Great display panels for our hotel',
    'Purchased 30 units for our hotel renovation. All panels arrived in perfect condition, installation was straightforward. Picture quality is stunning.',
    'approved', 18, NOW()),

(104, 105, 106, 5, 'Top-notch security system',
    'Installed these across 5 of our commercial properties. AI detection reduces false alarms dramatically. Mobile app is very responsive.',
    'approved', 31, NOW()),

(105, 106, 105, 5, 'Precision is remarkable',
    'Tested the 5-axis CNC machine for 3 months now. Accuracy is within ±0.01mm in practice. Technical support from Global Machinery is excellent.',
    'approved', 15, NOW()),

(106, 107, 106, 4, 'Solid compressor, great value',
    'Running this compressor 10 hours a day in our fabrication shop. Quiet, efficient, and uses less energy than our old machine. Very happy.',
    'approved', 8, NOW()),

(107, 111, 105, 5, 'Perfect T-shirts for our brand',
    'Ordered 2000 pieces with our logo. Print quality is vibrant and fabric is soft. Sizing runs true. Will definitely order again for next season.',
    'approved', 42, NOW()),

(108, 111, 106, 4, 'Good quality, color accuracy excellent',
    'Colors match Pantone specs very well. A couple of pieces had minor stitching issues but supplier replaced them immediately. Good partner.',
    'approved', 9, NOW()),

(109, 113, 105, 5, 'Activewear bestseller in our store',
    'This activewear set became our #1 bestseller within 2 months. Customers love the stretch and moisture-wicking performance. Excellent value.',
    'approved', 27, NOW()),

(110, 115, 106, 5, 'Packable jackets, customers love them',
    'Lightweight yet warm — our customers keep coming back for these jackets. Custom labeling was done perfectly. Very professional supplier.',
    'approved', 19, NOW()),

(111, 116, 105, 4, 'Smart bulbs work seamlessly',
    'Setup is easy and app control is smooth. Voice commands via Alexa work perfectly. A few bulbs in the batch had connectivity issues but were replaced.',
    'approved', 11, NOW()),

(112, 119, 106, 5, 'Best mattress at this price point',
    'Running a budget hotel. These mattresses have been comfortable and durable. Guests rarely complain about sleep quality now. Excellent investment.',
    'approved', 33, NOW()),

(113, 121, 105, 5, 'Premium tea quality',
    'Sold these in our organic food store — customers immediately noticed the difference in quality. Packaging is beautiful and reorders sell out fast.',
    'approved', 14, NOW()),

(114, 125, 106, 4, 'Protein bars selling well',
    'Added to our gym''s supplement range. Variety box is great for sampling. A few flavors are more popular than others but overall excellent seller.',
    'approved', 17, NOW()),

(115, 126, 105, 5, 'Outstanding vitamin C serum',
    'Private-labeled these for our skincare brand. Formula is professional grade and customer feedback has been excellent. Repeat order placed.',
    'approved', 56, NOW()),

(116, 126, 106, 5, 'Best serum we''ve sold',
    'Introduced this to our beauty salon chain. Results are visible within 2 weeks for clients. Elegant packaging and great stability.',
    'approved', 38, NOW()),

(117, 128, 105, 5, 'Massage gun is a game changer',
    'Restocked 3 times already — our gym members love it. Build quality feels premium and the carrying case is a nice touch for gifting.',
    'approved', 29, NOW()),

(118, 129, 106, 4, 'Reliable N95 masks',
    'Bulk purchased for our factory safety program. Fit is comfortable and workers wear them all day without complaints. Good filtration quality.',
    'approved', 22, NOW()),

(119, 130, 105, 5, 'Beautiful diffuser set — great gift',
    'Used these as corporate wellness gifts. Recipients loved the packaging and essential oil variety. Brand perception improved significantly.',
    'approved', 35, NOW()),

(120, 104, 106, 4, 'Solar power banks for our gift range',
    'Added to our promotional gift catalog. Solar charging is slower than wired but customers appreciate the eco-friendly angle. Good product.',
    'approved', 7, NOW());

-- ============================================================
-- 6. RFQs (5 sample RFQs)
-- ============================================================

INSERT IGNORE INTO `rfqs`
    (`id`, `rfq_number`, `buyer_id`, `title`, `description`, `category_id`,
     `quantity`, `unit`, `target_price`, `currency`, `destination_country`,
     `deadline`, `status`)
VALUES
(101, 'RFQ-2026-0001', 105,
    'Bluetooth Wireless Earbuds — 500 Units',
    'Looking for OEM Bluetooth earbuds with ANC, minimum 25hr battery, TWS design. Need CE and RoHS certification. Prefer suppliers from China or Taiwan.',
    101, 500, 'pair', 15.00, 'USD', 'Bangladesh',
    DATE_ADD(NOW(), INTERVAL 30 DAY), 'open'),

(102, 'RFQ-2026-0002', 106,
    'CNC Lathe Machine — 2 Units for Metal Workshop',
    'Require 2 units of heavy-duty CNC lathe machine, 2-meter bed length, with FANUC controller. Must include installation training and 2-year warranty.',
    102, 2, 'piece', 15000.00, 'USD', 'Australia',
    DATE_ADD(NOW(), INTERVAL 45 DAY), 'open'),

(103, 'RFQ-2026-0003', 105,
    'Custom Printed Cotton T-Shirts — 5000 pcs',
    'Need 5000 pieces of 180GSM cotton T-shirts with our company logo embroidery. Colors: Navy, White, Grey. Sizes: S, M, L, XL, XXL in equal ratio.',
    103, 5000, 'piece', 3.50, 'USD', 'United Kingdom',
    DATE_ADD(NOW(), INTERVAL 25 DAY), 'open'),

(104, 'RFQ-2026-0004', 106,
    'Vitamin C Serum Private Label — 2000 Bottles',
    'Seeking a manufacturer for private-label Vitamin C serum (20% concentration). Need custom packaging design, INCI list, and cosmetic safety report included.',
    108, 2000, 'bottle', 5.00, 'USD', 'Canada',
    DATE_ADD(NOW(), INTERVAL 60 DAY), 'closed'),

(105, 'RFQ-2026-0005', 105,
    'Organic Green Tea — 200 Cartons',
    'Require USDA organic certified green tea in retail-ready packaging for our health food chain. 100 bags per box, 12 boxes per carton. Need nutritional label and allergen statement.',
    105, 200, 'carton', 10.00, 'USD', 'United States',
    DATE_ADD(NOW(), INTERVAL 20 DAY), 'open');

-- ============================================================
-- 7. ORDERS (8 sample orders)
-- ============================================================

INSERT IGNORE INTO `orders`
    (`id`, `order_number`, `buyer_id`, `supplier_id`, `status`,
     `subtotal`, `shipping_fee`, `tax`, `discount`, `total`,
     `currency`, `payment_status`, `payment_method`,
     `shipping_address`, `placed_at`)
VALUES
(101, 'ORD-2026-000101', 105, 101, 'delivered',
    6250.00, 180.00, 125.00, 0.00, 6555.00,
    'USD', 'paid', 'bank_transfer',
    '{"name":"Ahmed Rahman","address":"House 12, Road 4, Dhanmondi","city":"Dhaka","country":"Bangladesh","postal_code":"1205"}',
    DATE_SUB(NOW(), INTERVAL 60 DAY)),

(102, 'ORD-2026-000102', 106, 101, 'delivered',
    22500.00, 850.00, 450.00, 500.00, 23300.00,
    'USD', 'paid', 'wire_transfer',
    '{"name":"Sarah Chen","address":"Level 8, 200 George Street","city":"Sydney","country":"Australia","postal_code":"2000"}',
    DATE_SUB(NOW(), INTERVAL 45 DAY)),

(103, 'ORD-2026-000103', 105, 103, 'shipped',
    1400.00, 120.00, 28.00, 0.00, 1548.00,
    'USD', 'paid', 'paypal',
    '{"name":"Ahmed Rahman","address":"House 12, Road 4, Dhanmondi","city":"Dhaka","country":"Bangladesh","postal_code":"1205"}',
    DATE_SUB(NOW(), INTERVAL 20 DAY)),

(104, 'ORD-2026-000104', 106, 102, 'confirmed',
    4800.00, 220.00, 96.00, 0.00, 5116.00,
    'USD', 'paid', 'bank_transfer',
    '{"name":"Sarah Chen","address":"Level 8, 200 George Street","city":"Sydney","country":"Australia","postal_code":"2000"}',
    DATE_SUB(NOW(), INTERVAL 10 DAY)),

(105, 'ORD-2026-000105', 105, 101, 'processing',
    725.00, 45.00, 14.50, 50.00, 734.50,
    'USD', 'paid', 'stripe',
    '{"name":"Ahmed Rahman","address":"House 12, Road 4, Dhanmondi","city":"Dhaka","country":"Bangladesh","postal_code":"1205"}',
    DATE_SUB(NOW(), INTERVAL 5 DAY)),

(106, 'ORD-2026-000106', 106, 103, 'pending',
    3000.00, 95.00, 60.00, 0.00, 3155.00,
    'USD', 'pending', 'bank_transfer',
    '{"name":"Sarah Chen","address":"Level 8, 200 George Street","city":"Sydney","country":"Australia","postal_code":"2000"}',
    DATE_SUB(NOW(), INTERVAL 2 DAY)),

(107, 'ORD-2026-000107', 105, 102, 'confirmed',
    9800.00, 350.00, 196.00, 0.00, 10346.00,
    'USD', 'paid', 'wire_transfer',
    '{"name":"Ahmed Rahman","address":"House 12, Road 4, Dhanmondi","city":"Dhaka","country":"Bangladesh","postal_code":"1205"}',
    DATE_SUB(NOW(), INTERVAL 15 DAY)),

(108, 'ORD-2026-000108', 106, 101, 'pending',
    1490.00, 75.00, 29.80, 0.00, 1594.80,
    'USD', 'pending', 'stripe',
    '{"name":"Sarah Chen","address":"Level 8, 200 George Street","city":"Sydney","country":"Australia","postal_code":"2000"}',
    DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ============================================================
-- 8. ORDER ITEMS
-- ============================================================

INSERT IGNORE INTO `order_items`
    (`id`, `order_id`, `product_id`, `product_name`, `product_sku`, `quantity`, `unit_price`, `total_price`)
VALUES
-- Order 101: Bluetooth Earbuds x500
(101, 101, 101, 'Wireless Bluetooth Earbuds Pro', 'TV-BT-001', 500, 12.50, 6250.00),

-- Order 102: Smart LED Panel x79 (≈22500/285)
(102, 102, 102, 'Smart LED Display Panel 55"',    'TV-LED-002', 79, 284.81, 22500.00),

-- Order 103: Custom T-shirts x500
(103, 103, 111, 'Custom Printed Cotton T-Shirts',  'FH-TSH-001', 500, 2.80, 1400.00),

-- Order 104: Air Compressor x1
(104, 104, 107, 'Industrial Air Compressor 500L',  'GM-AIR-002', 1, 4800.00, 4800.00),

-- Order 105: Smart bulb set x50
(105, 105, 116, 'Smart WiFi LED Bulb Set',         'TV-SLB-006', 50, 14.50, 725.00),

-- Order 106: Sports Activewear Set x337 (≈3000/8.9)
(106, 106, 113, 'Sports Activewear Set',           'FH-ACT-003', 337, 8.90, 2999.30),

-- Order 107: Welding Robot x1 (partial — rest of total)
(107, 107, 110, 'Industrial Welding Robot Arm',    'GM-ROB-005', 1, 9800.00, 9800.00),

-- Order 108: Security Camera System x6 + Smart bulbs x10
(108, 108, 105,  'Smart Security Camera System',   'TV-CAM-005', 5, 245.00, 1225.00),
(109, 108, 116,  'Smart WiFi LED Bulb Set',         'TV-SLB-006', 18, 14.50, 261.00);

-- ============================================================
-- 9. WISHLIST ITEMS (5 entries)
-- ============================================================

INSERT IGNORE INTO `wishlist_items` (`id`, `user_id`, `product_id`, `added_at`)
VALUES
(101, 105, 106, NOW()),
(102, 105, 115, NOW()),
(103, 105, 126, NOW()),
(104, 106, 102, NOW()),
(105, 106, 128, NOW());

-- ============================================================
-- 10. CART ITEMS (3 entries)
-- ============================================================

INSERT IGNORE INTO `cart_items` (`id`, `user_id`, `product_id`, `quantity`, `added_at`)
VALUES
(101, 105, 111, 200, NOW()),
(102, 105, 124, 100, NOW()),
(103, 106, 130, 30,  NOW());

-- ============================================================
-- Done
-- ============================================================

SET FOREIGN_KEY_CHECKS = 1;
