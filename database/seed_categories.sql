-- seed_categories.sql
-- PR #4: Full 3-Level Hierarchical Category Seed Data
-- 8 root categories, ~45 Level-2, ~100 Level-3

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE categories;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- LEVEL 1 — Root Categories
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(1,  NULL, 'Electronics',           'electronics',           'Electronic devices and accessories',          'bi-cpu-fill',          1, 8.00,  1, 1),
(2,  NULL, 'Fashion & Apparel',     'fashion-apparel',       'Clothing, shoes, bags and accessories',       'bi-bag-fill',          2, 15.00, 1, 1),
(3,  NULL, 'Industrial Equipment',  'industrial-equipment',  'Machinery, tools and industrial supplies',    'bi-tools',             3, 6.00,  1, 1),
(4,  NULL, 'Raw Materials',         'raw-materials',         'Metals, plastics, chemicals, textiles',       'bi-layers-fill',       4, 5.00,  1, 1),
(5,  NULL, 'Food & Agriculture',    'food-agriculture',      'Food products, fresh produce, agri goods',   'bi-basket-fill',       5, 10.00, 1, 1),
(6,  NULL, 'Home & Living',         'home-living',           'Furniture, decor and household items',        'bi-house-fill',        6, 12.00, 1, 1),
(7,  NULL, 'Health & Beauty',       'health-beauty',         'Healthcare products and cosmetics',           'bi-heart-fill',        7, 13.00, 1, 1),
(8,  NULL, 'Documents & Services',  'documents-services',    'Professional services and documentation',     'bi-file-earmark-fill', 8, 7.00,  1, 1);

-- ============================================================
-- LEVEL 2 — Electronics (parent_id = 1)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(10, 1, 'Phones',         'phones',         'Mobile phones and accessories',         'bi-phone-fill',      1, NULL, 2, 1),
(11, 1, 'Computers',      'computers',      'Laptops, desktops, tablets',            'bi-laptop-fill',     2, NULL, 2, 1),
(12, 1, 'Audio',          'audio',          'Headphones, speakers, microphones',     'bi-headphones',      3, NULL, 2, 1),
(13, 1, 'Cameras',        'cameras',        'Digital cameras and accessories',       'bi-camera-fill',     4, NULL, 2, 1),
(14, 1, 'Wearables',      'wearables',      'Smartwatches, fitness trackers',        'bi-watch',           5, NULL, 2, 1),
(15, 1, 'Smart Home',     'smart-home',     'Smart devices and home automation',     'bi-house-gear-fill', 6, NULL, 2, 1),
(16, 1, 'Accessories',    'elec-accessories','Cables, chargers, cases, screen protectors','bi-plug-fill',  7, NULL, 2, 1);

-- ============================================================
-- LEVEL 2 — Fashion & Apparel (parent_id = 2)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(20, 2, "Men's Clothing",    'mens-clothing',    "Men's shirts, trousers, suits",         'bi-person-fill',     1, NULL, 2, 1),
(21, 2, "Women's Clothing",  'womens-clothing',  "Women's dresses, tops, skirts",         'bi-person-dress-fill',2, NULL, 2, 1),
(22, 2, "Children's",        'childrens',        "Kids' clothing and accessories",         'bi-emoji-smile-fill', 3, NULL, 2, 1),
(23, 2, 'Shoes',             'shoes',            'Footwear for all occasions',            'bi-bootstrap-fill',   4, NULL, 2, 1),
(24, 2, 'Bags',              'bags',             'Handbags, backpacks, wallets',          'bi-handbag-fill',     5, NULL, 2, 1),
(25, 2, 'Jewelry',           'jewelry',          'Necklaces, rings, earrings',            'bi-gem',              6, NULL, 2, 1),
(26, 2, 'Watches',           'watches',          'Luxury and fashion watches',            'bi-watch',            7, NULL, 2, 1);

-- ============================================================
-- LEVEL 2 — Industrial Equipment (parent_id = 3)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(30, 3, 'Machinery',          'machinery',          'Heavy and light industrial machinery',  'bi-gear-wide-connected', 1, NULL, 2, 1),
(31, 3, 'Hand Tools',         'hand-tools',         'Manual tools for industrial use',       'bi-wrench-adjustable',   2, NULL, 2, 1),
(32, 3, 'Safety Equipment',   'safety-equipment',   'PPE and safety gear',                   'bi-shield-fill',         3, NULL, 2, 1),
(33, 3, 'Electrical',         'electrical',         'Cables, panels, switches',              'bi-lightning-charge-fill',4, NULL, 2, 1),
(34, 3, 'Hydraulics',         'hydraulics',         'Pumps, cylinders, fittings',            'bi-droplet-fill',        5, NULL, 2, 1);

-- ============================================================
-- LEVEL 2 — Raw Materials (parent_id = 4)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(40, 4, 'Metals',            'metals',            'Steel, aluminum, copper, iron',         'bi-coin',           1, NULL, 2, 1),
(41, 4, 'Plastics',          'plastics',          'Plastic resins and sheets',             'bi-box-fill',       2, NULL, 2, 1),
(42, 4, 'Chemicals',         'chemicals',         'Industrial and consumer chemicals',     'bi-eyedropper',     3, NULL, 2, 1),
(43, 4, 'Textiles',          'textiles',          'Fabric, yarn, threads',                 'bi-scissors',       4, NULL, 2, 1);

-- ============================================================
-- LEVEL 2 — Food & Agriculture (parent_id = 5)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(50, 5, 'Fresh Produce',     'fresh-produce',     'Fruits, vegetables, dairy',             'bi-apple',           1, NULL, 2, 1),
(51, 5, 'Grains & Cereals',  'grains-cereals',    'Rice, wheat, corn, flour',              'bi-brightness-high', 2, NULL, 2, 1),
(52, 5, 'Meat & Seafood',    'meat-seafood',      'Fresh and frozen meat and fish',        'bi-egg-fill',        3, NULL, 2, 1),
(53, 5, 'Packaged Food',     'packaged-food',     'Processed and packaged food items',     'bi-bag-dash-fill',   4, NULL, 2, 1),
(54, 5, 'Beverages',         'beverages',         'Water, juices, teas, energy drinks',   'bi-cup-fill',        5, NULL, 2, 1),
(55, 5, 'Agricultural Equipment', 'agri-equipment','Tractors, irrigation, farm tools',    'bi-flower2',         6, NULL, 2, 1);

-- ============================================================
-- LEVEL 2 — Home & Living (parent_id = 6)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(60, 6, 'Furniture',         'furniture',         'Sofas, beds, tables, chairs',           'bi-house-door-fill', 1, NULL, 2, 1),
(61, 6, 'Kitchen & Dining',  'kitchen-dining',    'Cookware, utensils, appliances',        'bi-cup-hot-fill',    2, NULL, 2, 1),
(62, 6, 'Bedding',           'bedding',           'Pillows, blankets, bed sheets',         'bi-moon-stars-fill', 3, NULL, 2, 1),
(63, 6, 'Decor',             'decor',             'Art, candles, photo frames',            'bi-palette-fill',    4, NULL, 2, 1),
(64, 6, 'Garden & Outdoor',  'garden-outdoor',    'Plants, garden tools, outdoor furniture','bi-tree-fill',      5, NULL, 2, 1),
(65, 6, 'Lighting',          'lighting',          'Lamps, bulbs, smart lighting',          'bi-lightbulb-fill',  6, NULL, 2, 1);

-- ============================================================
-- LEVEL 2 — Health & Beauty (parent_id = 7)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(70, 7, 'Skincare',          'skincare',          'Moisturizers, serums, sunscreen',       'bi-heart-pulse-fill',1, NULL, 2, 1),
(71, 7, 'Haircare',          'haircare',          'Shampoo, conditioner, styling',         'bi-stars',           2, NULL, 2, 1),
(72, 7, 'Makeup',            'makeup',            'Foundation, lipstick, eye makeup',      'bi-magic',           3, NULL, 2, 1),
(73, 7, 'Vitamins & Supplements','vitamins-supplements','Health supplements and vitamins', 'bi-capsule-pill',    4, NULL, 2, 1),
(74, 7, 'Medical Devices',   'medical-devices',   'Blood pressure monitors, thermometers', 'bi-activity',        5, NULL, 2, 1),
(75, 7, 'Personal Care',     'personal-care',     'Toothbrush, deodorant, razors',         'bi-person-bounding-box',6, NULL, 2, 1);

-- ============================================================
-- LEVEL 2 — Documents & Services (parent_id = 8)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(80, 8, 'Trade Documents',   'trade-documents',   'Export/import certificates and docs',   'bi-file-earmark-text-fill', 1, NULL, 2, 1),
(81, 8, 'Legal Services',    'legal-services',    'Contracts, legal consultation',         'bi-briefcase-fill',         2, NULL, 2, 1),
(82, 8, 'Logistics Services','logistics-services','Freight forwarding, customs',           'bi-truck-fill',             3, NULL, 2, 1),
(83, 8, 'Consulting',        'consulting',        'Business and trade consulting',         'bi-person-workspace',       4, NULL, 2, 1);

-- ============================================================
-- LEVEL 3 — Phones → children (parent_id = 10)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(101, 10, 'Smartphones',        'smartphones',        'Android and iOS smartphones',           NULL, 1, NULL, 3, 1),
(102, 10, 'Feature Phones',     'feature-phones',     'Basic mobile phones',                   NULL, 2, NULL, 3, 1),
(103, 10, 'Phone Accessories',  'phone-accessories',  'Cables, chargers, earphones',           NULL, 3, NULL, 3, 1),
(104, 10, 'Phone Cases',        'phone-cases',        'Protective cases and covers',           NULL, 4, NULL, 3, 1),
(105, 10, 'Screen Protectors',  'screen-protectors',  'Tempered glass and film protectors',    NULL, 5, NULL, 3, 1);

-- ============================================================
-- LEVEL 3 — Computers → children (parent_id = 11)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(110, 11, 'Laptops',         'laptops',         'Portable computers',                    NULL, 1, NULL, 3, 1),
(111, 11, 'Desktops',        'desktops',        'Desktop PCs and workstations',          NULL, 2, NULL, 3, 1),
(112, 11, 'Tablets',         'tablets',         'iPad, Android, Windows tablets',        NULL, 3, NULL, 3, 1),
(113, 11, 'Monitors',        'monitors',        'LED, IPS, 4K displays',                 NULL, 4, NULL, 3, 1),
(114, 11, 'Computer Parts',  'computer-parts',  'CPU, RAM, storage, GPU',                NULL, 5, NULL, 3, 1),
(115, 11, 'Networking',      'networking',      'Routers, switches, cables',             NULL, 6, NULL, 3, 1);

-- ============================================================
-- LEVEL 3 — Audio → children (parent_id = 12)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(120, 12, 'Headphones',      'headphones',      'Over-ear and on-ear headphones',        NULL, 1, NULL, 3, 1),
(121, 12, 'Earbuds',         'earbuds',         'In-ear earbuds and TWS earphones',      NULL, 2, NULL, 3, 1),
(122, 12, 'Speakers',        'speakers',        'Bluetooth and wired speakers',          NULL, 3, NULL, 3, 1),
(123, 12, 'Microphones',     'microphones',     'Recording and gaming microphones',      NULL, 4, NULL, 3, 1);

-- ============================================================
-- LEVEL 3 — Cameras → children (parent_id = 13)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(130, 13, 'DSLR Cameras',    'dslr-cameras',    'Digital SLR cameras',                   NULL, 1, NULL, 3, 1),
(131, 13, 'Mirrorless',      'mirrorless',      'Mirrorless system cameras',             NULL, 2, NULL, 3, 1),
(132, 13, 'Action Cameras',  'action-cameras',  'GoPro-style action cameras',            NULL, 3, NULL, 3, 1),
(133, 13, 'Lenses',          'lenses',          'Camera lenses and adapters',            NULL, 4, NULL, 3, 1),
(134, 13, 'Camera Bags',     'camera-bags',     'Cases and bags for cameras',            NULL, 5, NULL, 3, 1);

-- ============================================================
-- LEVEL 3 — Wearables → children (parent_id = 14)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(140, 14, 'Smartwatches',    'smartwatches',    'Smart watches with app support',        NULL, 1, NULL, 3, 1),
(141, 14, 'Fitness Trackers','fitness-trackers','Activity bands and step counters',      NULL, 2, NULL, 3, 1),
(142, 14, 'Smart Glasses',   'smart-glasses',   'AR and smart eyewear',                  NULL, 3, NULL, 3, 1);

-- ============================================================
-- LEVEL 3 — Smart Home → children (parent_id = 15)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(150, 15, 'Smart Lighting',    'smart-lighting',    'Smart bulbs and lighting systems',  NULL, 1, NULL, 3, 1),
(151, 15, 'Smart Security',    'smart-security',    'Smart cameras and alarm systems',   NULL, 2, NULL, 3, 1),
(152, 15, 'Smart Speakers',    'smart-speakers',    'Alexa, Google Home devices',        NULL, 3, NULL, 3, 1),
(153, 15, 'Smart Appliances',  'smart-appliances',  'Connected fridges, ovens, ACs',     NULL, 4, NULL, 3, 1);

-- ============================================================
-- LEVEL 3 — Men's Clothing → children (parent_id = 20)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(200, 20, 'T-Shirts',        'mens-t-shirts',   'Casual and graphic tees',               NULL, 1, NULL, 3, 1),
(201, 20, 'Suits & Blazers', 'suits-blazers',   'Formal suits and blazers',              NULL, 2, NULL, 3, 1),
(202, 20, 'Trousers',        'trousers',        'Chinos, jeans, formal trousers',        NULL, 3, NULL, 3, 1),
(203, 20, 'Sportswear',      'mens-sportswear', 'Athletic clothes and activewear',       NULL, 4, NULL, 3, 1);

-- ============================================================
-- LEVEL 3 — Women's Clothing → children (parent_id = 21)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(210, 21, 'Dresses',         'dresses',         'Casual, formal and party dresses',      NULL, 1, NULL, 3, 1),
(211, 21, 'Tops & Blouses',  'tops-blouses',    'T-shirts, blouses, tank tops',          NULL, 2, NULL, 3, 1),
(212, 21, 'Skirts',          'skirts',          'Mini, midi and maxi skirts',            NULL, 3, NULL, 3, 1),
(213, 21, 'Sportswear',      'womens-sportswear','Yoga wear, gym clothes',               NULL, 4, NULL, 3, 1);

-- ============================================================
-- LEVEL 3 — Machinery → children (parent_id = 30)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(300, 30, 'Metalworking',    'metalworking',    'CNC, lathes, milling machines',         NULL, 1, NULL, 3, 1),
(301, 30, 'Woodworking',     'woodworking',     'Saws, routers, planers',                NULL, 2, NULL, 3, 1),
(302, 30, 'Packaging',       'packaging-machines','Filling, sealing, labeling machines', NULL, 3, NULL, 3, 1),
(303, 30, 'Printing',        'printing-machines','Offset, digital, screen printers',     NULL, 4, NULL, 3, 1);

-- ============================================================
-- LEVEL 3 — Safety Equipment → children (parent_id = 32)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(320, 32, 'Head Protection',   'head-protection',   'Helmets and hard hats',             NULL, 1, NULL, 3, 1),
(321, 32, 'Eye Protection',    'eye-protection',    'Safety glasses and goggles',        NULL, 2, NULL, 3, 1),
(322, 32, 'Hand Protection',   'hand-protection',   'Safety gloves',                     NULL, 3, NULL, 3, 1),
(323, 32, 'Foot Protection',   'foot-protection',   'Safety boots and shoes',            NULL, 4, NULL, 3, 1);

-- ============================================================
-- LEVEL 3 — Fresh Produce → children (parent_id = 50)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(500, 50, 'Fruits',           'fruits',           'Fresh tropical and temperate fruits', NULL, 1, NULL, 3, 1),
(501, 50, 'Vegetables',       'vegetables',       'Fresh leafy greens and root veg',     NULL, 2, NULL, 3, 1),
(502, 50, 'Dairy',            'dairy',            'Milk, cheese, yogurt, butter',        NULL, 3, NULL, 3, 1),
(503, 50, 'Eggs',             'eggs',             'Chicken, duck and quail eggs',        NULL, 4, NULL, 3, 1);

-- ============================================================
-- LEVEL 3 — Furniture → children (parent_id = 60)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(600, 60, 'Living Room',      'living-room',      'Sofas, coffee tables, TV stands',     NULL, 1, NULL, 3, 1),
(601, 60, 'Bedroom',          'bedroom-furniture','Beds, wardrobes, dressers',            NULL, 2, NULL, 3, 1),
(602, 60, 'Office Furniture', 'office-furniture', 'Desks, chairs, storage',              NULL, 3, NULL, 3, 1),
(603, 60, 'Outdoor',          'outdoor-furniture','Patio sets, garden chairs',            NULL, 4, NULL, 3, 1);

-- ============================================================
-- LEVEL 3 — Kitchen & Dining → children (parent_id = 61)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(610, 61, 'Cookware',         'cookware',         'Pots, pans, woks',                    NULL, 1, NULL, 3, 1),
(611, 61, 'Kitchen Appliances','kitchen-appliances','Blenders, microwaves, toasters',    NULL, 2, NULL, 3, 1),
(612, 61, 'Tableware',        'tableware',        'Plates, bowls, glasses',              NULL, 3, NULL, 3, 1),
(613, 61, 'Utensils',         'utensils',         'Knives, spatulas, ladles',            NULL, 4, NULL, 3, 1);

-- ============================================================
-- LEVEL 3 — Skincare → children (parent_id = 70)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(700, 70, 'Moisturizers',    'moisturizers',    'Day and night moisturizers',            NULL, 1, NULL, 3, 1),
(701, 70, 'Serums',          'serums',          'Vitamin C, retinol, hyaluronic acid',   NULL, 2, NULL, 3, 1),
(702, 70, 'Sunscreen',       'sunscreen',       'SPF 30, 50+ sunscreens',                NULL, 3, NULL, 3, 1),
(703, 70, 'Cleansers',       'cleansers',       'Face wash and micellar water',          NULL, 4, NULL, 3, 1);

-- ============================================================
-- LEVEL 3 — Trade Documents → children (parent_id = 80)
-- ============================================================
INSERT INTO categories (id, parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active)
VALUES
(800, 80, 'Export Certificates','export-certificates','Certificate of origin, phytosanitary', NULL, 1, NULL, 3, 1),
(801, 80, 'Import Licenses',  'import-licenses',  'Import permits and customs forms',    NULL, 2, NULL, 3, 1),
(802, 80, 'Quality Certificates','quality-certificates','ISO, CE, FDA certificates',     NULL, 3, NULL, 3, 1);
