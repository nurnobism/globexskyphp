-- schema_v17_tracking.sql — Parcel Tracking Integration (PR #15)
-- Creates: shipping_carriers, shipments, shipment_events tables

-- ── Carriers ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `shipping_carriers` (
    `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code`                 VARCHAR(32)  NOT NULL UNIQUE,
    `name`                 VARCHAR(100) NOT NULL,
    `logo_url`             VARCHAR(255) NOT NULL DEFAULT '',
    `tracking_url_template` VARCHAR(512) NOT NULL DEFAULT '',
    `api_endpoint`         VARCHAR(512) NOT NULL DEFAULT '',
    `api_key_setting`      VARCHAR(100) NOT NULL DEFAULT '',
    `is_active`            TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`           SMALLINT     NOT NULL DEFAULT 0,
    `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sc_code` (`code`),
    INDEX `idx_sc_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed carriers
INSERT IGNORE INTO `shipping_carriers`
    (`code`, `name`, `logo_url`, `tracking_url_template`, `api_endpoint`, `api_key_setting`, `is_active`, `sort_order`)
VALUES
    ('dhl',           'DHL',           '/assets/carriers/dhl.png',           'https://www.dhl.com/en/express/tracking.html?AWB={tracking_number}',      'https://api-eu.dhl.com/track/shipments',      'carrier_api_dhl',           1, 10),
    ('fedex',         'FedEx',         '/assets/carriers/fedex.png',         'https://www.fedex.com/fedextrack/?trknbr={tracking_number}',               'https://apis.fedex.com/track/v1/trackingnumbers', 'carrier_api_fedex',      1, 20),
    ('ups',           'UPS',           '/assets/carriers/ups.png',           'https://www.ups.com/track?tracknum={tracking_number}',                     'https://onlinetools.ups.com/api/track/v1/details/{tracking_number}', 'carrier_api_ups', 1, 30),
    ('usps',          'USPS',          '/assets/carriers/usps.png',          'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking_number}',   'https://secure.shippingapis.com/ShippingAPI.dll', 'carrier_api_usps',      1, 40),
    ('royal_mail',    'Royal Mail',    '/assets/carriers/royal_mail.png',    'https://www3.royalmail.com/track-your-item#/tracking-results/{tracking_number}', '',                                      'carrier_api_royal_mail',    1, 50),
    ('china_post',    'China Post',    '/assets/carriers/china_post.png',    'https://yjcx.ems.com.cn/qps/english/yjcx?mailNo={tracking_number}',        '',                                            'carrier_api_china_post',    1, 60),
    ('bangladesh_post','Bangladesh Post','/assets/carriers/bd_post.png',    'https://www.bangladeshpost.gov.bd/posts/tracking.asp?TrackNumber={tracking_number}', '',                                 'carrier_api_bd_post',       1, 70),
    ('aramex',        'Aramex',        '/assets/carriers/aramex.png',        'https://www.aramex.com/us/en/track/results?mode=0&ShipmentNumber={tracking_number}', 'https://ws.aramex.net/ShippingAPI.V2/Tracking/Service_1_0.svc/json/TrackShipments', 'carrier_api_aramex', 1, 80),
    ('generic',       'Other Carrier', '/assets/carriers/generic.png',       '{tracking_url}',                                                           '',                                            '',                          1, 90);

-- ── Shipments ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `shipments` (
    `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id`            INT UNSIGNED NOT NULL,
    `supplier_id`         INT UNSIGNED NOT NULL,
    `carrier_code`        VARCHAR(32)  NOT NULL DEFAULT 'generic',
    `carrier_name`        VARCHAR(100) NOT NULL DEFAULT '',
    `tracking_number`     VARCHAR(128) NOT NULL DEFAULT '',
    `tracking_url`        VARCHAR(512) NOT NULL DEFAULT '',
    `status`              ENUM('label_created','picked_up','in_transit','out_for_delivery','delivered','exception','returned','unknown')
                          NOT NULL DEFAULT 'label_created',
    `shipped_date`        DATETIME     NULL,
    `estimated_delivery`  DATE         NULL,
    `actual_delivery`     DATETIME     NULL,
    `weight_kg`           DECIMAL(8,3) NULL,
    `package_dimensions`  VARCHAR(100) NOT NULL DEFAULT '',
    `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_shipments_order`    (`order_id`),
    INDEX `idx_shipments_supplier` (`supplier_id`),
    INDEX `idx_shipments_tracking` (`tracking_number`),
    INDEX `idx_shipments_status`   (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Shipment Events ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `shipment_events` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `shipment_id`   INT UNSIGNED NOT NULL,
    `status`        VARCHAR(64)  NOT NULL DEFAULT '',
    `location`      VARCHAR(255) NOT NULL DEFAULT '',
    `description`   TEXT         NOT NULL,
    `event_date`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `raw_data_json` TEXT         NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_shipment_events_shipment` (`shipment_id`),
    INDEX `idx_shipment_events_date`     (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
