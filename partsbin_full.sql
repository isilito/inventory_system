-- PartsBin: full database (tables + sample data)
-- Paste into phpMyAdmin > SQL tab and click Go. Re-running it resets the data.
-- Demo login: admin / admin123

CREATE DATABASE IF NOT EXISTS `partsbin` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `partsbin`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `stock_movements`;
DROP TABLE IF EXISTS `variants`;
DROP TABLE IF EXISTS `items`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;


-- Accounts that can log in. `password` holds a password_hash(), never plain text.
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    full_name  VARCHAR(100) NOT NULL,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    email      VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    avatar     VARCHAR(255) NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A product. `image` is the file name of its picture inside uploads/products/
CREATE TABLE IF NOT EXISTS items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NULL,
    name        VARCHAR(150) NOT NULL,
    brand       VARCHAR(100) NOT NULL DEFAULT '',
    status      VARCHAR(30)  NOT NULL DEFAULT 'New',
    notes       TEXT,
    image       VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Each item has one or more variants (capacity, color, model...).
-- Stock, cost and price live on the variant.
CREATE TABLE IF NOT EXISTS variants (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    item_id       INT           NOT NULL,
    label         VARCHAR(100)  NOT NULL,
    sku           VARCHAR(60)   NOT NULL DEFAULT '',
    quantity      INT           NOT NULL DEFAULT 0,
    unit_cost     DECIMAL(10,2) NOT NULL DEFAULT 0,
    sell_price    DECIMAL(10,2) NOT NULL DEFAULT 0,
    low_threshold INT           NOT NULL DEFAULT 2,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every change to stock is logged here (opening counts, restocks, adjustments)
CREATE TABLE IF NOT EXISTS stock_movements (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    variant_id INT           NOT NULL,
    user_id    INT           NULL,
    type       VARCHAR(20)   NOT NULL,
    qty_change INT           NOT NULL,
    qty_after  INT           NOT NULL,
    unit_cost  DECIMAL(10,2) NOT NULL DEFAULT 0,
    supplier   VARCHAR(150)  NOT NULL DEFAULT '',
    reason     VARCHAR(255)  NOT NULL DEFAULT '',
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (variant_id) REFERENCES variants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------- Demo account ----------
INSERT INTO users (id, full_name, username, email, password) VALUES (1, 'Shop Admin', 'admin', 'admin@partsbin.local', '$2y$10$RsiaRH7TETQmJVxrOL97C.dpf3S4scVTx76pQAbtRWDYY.P64yIh2');

-- ---------- Categories ----------
INSERT INTO categories (id, name, description) VALUES
  (1, 'Processors', 'Desktop CPUs'),
  (2, 'Graphics Cards', 'Dedicated GPUs'),
  (3, 'Memory', 'Desktop RAM kits'),
  (4, 'Storage', 'SSDs and NAS hard drives'),
  (5, 'Power Supplies', 'ATX PSUs'),
  (6, 'Monitors', 'Office, creator and gaming displays'),
  (7, 'Peripherals', 'Mice, keyboards and webcams');

-- ---------- Products ----------
INSERT INTO items (id, category_id, name, brand, status, notes, image, created_at) VALUES
  (1, 1, 'Core Ultra 5 Desktop Processor', 'Intel', 'New', '', 'products_sample_1_cpu-ultra5.jpg', DATE_SUB(NOW(), INTERVAL 31 DAY)),
  (2, 1, 'Core Ultra 7 Desktop Processor', 'Intel', 'New', '', 'products_sample_2_cpu-ultra7.jpg', DATE_SUB(NOW(), INTERVAL 32 DAY)),
  (3, 1, 'Core Ultra 9 285K Desktop Processor', 'Intel', 'New', '', 'products_sample_3_cpu-ultra9.jpg', DATE_SUB(NOW(), INTERVAL 33 DAY)),
  (4, 1, 'Ryzen 5 9000 Series Desktop Processor', 'AMD', 'New', '', 'products_sample_4_amd-ryzen5.jpg', DATE_SUB(NOW(), INTERVAL 34 DAY)),
  (5, 1, 'Ryzen 7 9000 Series Desktop Processor', 'AMD', 'New', '', 'products_sample_5_amd-ryzen7.jpg', DATE_SUB(NOW(), INTERVAL 35 DAY)),
  (6, 1, 'Ryzen 9 9000 Series Desktop Processor', 'AMD', 'New', '', 'products_sample_6_amd-ryzen9.jpg', DATE_SUB(NOW(), INTERVAL 36 DAY)),
  (7, 2, 'Radeon RX 9070 Series Graphics Card', 'AMD', 'New', '', 'products_sample_7_amd-rx9070.jpg', DATE_SUB(NOW(), INTERVAL 37 DAY)),
  (8, 2, 'Radeon RX 9060 XT Graphics Card', 'AMD', 'New', '', 'products_sample_8_amd-rx9060.jpg', DATE_SUB(NOW(), INTERVAL 38 DAY)),
  (9, 2, 'GeForce RTX 5070 Ventus 2X OC 12GB', 'MSI', 'New', '', 'products_sample_9_gpu-5070.jpg', DATE_SUB(NOW(), INTERVAL 39 DAY)),
  (10, 2, 'GeForce RTX 5080 Ventus 3X OC 16GB', 'MSI', 'New', '', 'products_sample_10_gpu-5080.jpg', DATE_SUB(NOW(), INTERVAL 40 DAY)),
  (11, 3, 'HT100 DDR4 Desktop Memory', 'Acer', 'Discontinued', '', 'products_sample_11_ram-acer.jpg', DATE_SUB(NOW(), INTERVAL 41 DAY)),
  (12, 4, 'IronWolf NAS Hard Drive', 'Seagate', 'New', '', 'products_sample_12_hdd-ironwolf.jpg', DATE_SUB(NOW(), INTERVAL 42 DAY)),
  (13, 5, 'MAG A750BN 80+ Bronze Power Supply', 'MSI', 'New', '', 'products_sample_13_psu-a750bn.jpg', DATE_SUB(NOW(), INTERVAL 43 DAY)),
  (14, 6, 'IPS Monitor', 'AOpen', 'New', '', 'products_sample_14_mon-aopen.jpg', DATE_SUB(NOW(), INTERVAL 44 DAY)),
  (15, 6, 'ProArt Display PA278CV 27" Monitor', 'ASUS', 'New', '', 'products_sample_15_mon-proart.jpg', DATE_SUB(NOW(), INTERVAL 45 DAY)),
  (16, 6, 'ROG Strix XG27ACMES 27" Gaming Monitor', 'ASUS', 'New', '', 'products_sample_16_asus-xg27acmes.jpg', DATE_SUB(NOW(), INTERVAL 46 DAY)),
  (17, 6, 'ROG Swift OLED PG27AQDM 27" Gaming Monitor', 'ASUS', 'New', '', 'products_sample_17_asus-pg27aqdm.jpg', DATE_SUB(NOW(), INTERVAL 47 DAY)),
  (18, 6, 'ROG Swift OLED PG27AQDP 27" Gaming Monitor', 'ASUS', 'New', '', 'products_sample_18_asus-pg27aqdp.jpg', DATE_SUB(NOW(), INTERVAL 48 DAY)),
  (19, 7, 'G304 Lightspeed Wireless Gaming Mouse', 'Logitech', 'New', '', 'products_sample_19_mouse-g304.jpg', DATE_SUB(NOW(), INTERVAL 49 DAY)),
  (20, 7, 'TUF Gaming M3 Gen II Gaming Mouse', 'ASUS', 'New', '', 'products_sample_20_mouse-m3.jpg', DATE_SUB(NOW(), INTERVAL 50 DAY)),
  (21, 7, 'TUF Gaming K1 RGB Gaming Keyboard', 'ASUS', 'New', '', 'products_sample_21_kb-tufk1.jpg', DATE_SUB(NOW(), INTERVAL 51 DAY)),
  (22, 7, 'C922 Pro HD Stream Webcam', 'Logitech', 'Used', '', 'products_sample_22_cam-c922.jpg', DATE_SUB(NOW(), INTERVAL 52 DAY));

-- ---------- Variants (stock, cost, price) ----------
INSERT INTO variants (id, item_id, label, sku, quantity, unit_cost, sell_price, low_threshold) VALUES
  (1, 1, '225 (up to 4.9GHz)', 'CPU-INT-U5-225', 4, 10100.00, 11900.00, 2),
  (2, 1, '225F (no iGPU)', 'CPU-INT-U5-225F', 3, 10150.00, 11950.00, 2),
  (3, 1, '245K Unlocked', 'CPU-INT-U5-245K', 2, 12400.00, 14600.00, 2),
  (4, 1, '245KF Unlocked (no iGPU)', 'CPU-INT-U5-245KF', 0, 11550.00, 13600.00, 1),
  (5, 2, '265', 'CPU-INT-U7-265', 2, 20100.00, 23650.00, 1),
  (6, 2, '265K Unlocked', 'CPU-INT-U7-265K', 3, 17700.00, 20850.00, 1),
  (7, 3, 'Boxed', 'CPU-INT-U9-285K', 1, 34100.00, 40150.00, 1),
  (8, 4, '9600X (6 cores, up to 5.4GHz)', 'CPU-AMD-R5-9600X', 5, 11500.00, 13500.00, 2),
  (9, 4, '9600 (6 cores, up to 5.2GHz)', 'CPU-AMD-R5-9600', 3, 10200.00, 12000.00, 2),
  (10, 5, '9700X (8 cores, up to 5.5GHz)', 'CPU-AMD-R7-9700X', 3, 16600.00, 19500.00, 2),
  (11, 5, '9800X3D (8 cores, 3D V-Cache)', 'CPU-AMD-R7-9800X3D', 2, 26000.00, 30500.00, 2),
  (12, 6, '9900X (12 cores)', 'CPU-AMD-R9-9900X', 2, 21700.00, 25500.00, 1),
  (13, 6, '9950X (16 cores)', 'CPU-AMD-R9-9950X', 1, 28900.00, 34000.00, 1),
  (14, 6, '9950X3D (16 cores, 3D V-Cache)', 'CPU-AMD-R9-9950X3D', 0, 37400.00, 44000.00, 1),
  (15, 7, 'RX 9070 XT 16GB GDDR6', 'GPU-AMD-RX9070XT', 3, 35700.00, 42000.00, 1),
  (16, 7, 'RX 9070 16GB GDDR6', 'GPU-AMD-RX9070', 2, 31000.00, 36500.00, 1),
  (17, 8, '16GB GDDR6', 'GPU-AMD-RX9060XT-16', 4, 20800.00, 24500.00, 2),
  (18, 8, '8GB GDDR6', 'GPU-AMD-RX9060XT-8', 1, 16600.00, 19500.00, 2),
  (19, 9, 'White (G5070-12V2CW)', 'GPU-MSI-5070-V2XW', 2, 54400.00, 64000.00, 1),
  (20, 10, 'G5080-16V3C', 'GPU-MSI-5080-V3X', 1, 125000.00, 147000.00, 1),
  (21, 11, '16GB 3200MHz', 'RAM-ACER-HT100-16', 1, 5800.00, 6800.00, 2),
  (22, 12, '2TB (ST2000VN004)', 'HDD-IW-2T', 4, 11000.00, 12950.00, 2),
  (23, 12, '4TB (ST4000VN006)', 'HDD-IW-4T', 3, 13400.00, 15800.00, 2),
  (24, 12, '8TB 7200RPM', 'HDD-IW-8T', 1, 23300.00, 27400.00, 1),
  (25, 12, '12TB (ST12000VN0008)', 'HDD-IW-12T', 0, 33100.00, 38900.00, 1),
  (26, 13, '750W Semi-Modular', 'PSU-MSI-A750BN', 5, 3300.00, 3900.00, 2),
  (27, 14, '19.5" 20E0Q BI 75Hz', 'MN-AOP-20E0Q', 3, 2300.00, 2700.00, 1),
  (28, 14, '23.8" 24CL1Y GBI', 'MN-AOP-24CL1Y', 4, 3270.00, 3850.00, 2),
  (29, 14, '27" 27SA3 G0BI', 'MN-AOP-27SA3', 2, 4500.00, 5300.00, 1),
  (30, 15, '27" WQHD', 'MN-ASUS-PA278CV', 1, 20650.00, 24300.00, 1),
  (31, 16, '27" 1440p 255Hz Fast IPS', 'MN-ASUS-XG27ACMES', 4, 18700.00, 22000.00, 2),
  (32, 17, '27" 1440p 240Hz OLED', 'MN-ASUS-PG27AQDM', 2, 39100.00, 46000.00, 1),
  (33, 18, '27" 1440p 480Hz OLED', 'MN-ASUS-PG27AQDP', 1, 51000.00, 60000.00, 1),
  (34, 19, 'Black', 'MS-LOGI-G304', 12, 1500.00, 1780.00, 4),
  (35, 20, 'P309 RGB USB Wired', 'MS-ASUS-M3G2', 6, 760.00, 900.00, 3),
  (36, 21, 'US layout', 'KB-ASUS-TUFK1', 2, 1700.00, 2000.00, 2),
  (37, 22, '1080p', 'CAM-LOGI-C922', 3, 5240.00, 6160.00, 2);

-- ---------- Stock history ----------
INSERT INTO stock_movements (variant_id, user_id, type, qty_change, qty_after, unit_cost, supplier, reason, created_at) VALUES
  (1, NULL, 'initial', 2, 2, 10100.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (1, NULL, 'restock', 2, 4, 10100.00, 'PC Hub Wholesale', 'PO-1001', DATE_SUB(NOW(), INTERVAL 5 DAY)),
  (2, NULL, 'initial', 2, 2, 10150.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (2, NULL, 'restock', 1, 3, 10150.00, 'Metro Components', 'PO-1002', DATE_SUB(NOW(), INTERVAL 8 DAY)),
  (3, NULL, 'initial', 1, 1, 12400.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (3, NULL, 'restock', 1, 2, 12400.00, 'TechSource Distribution', 'PO-1003', DATE_SUB(NOW(), INTERVAL 11 DAY)),
  (5, NULL, 'initial', 1, 1, 20100.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (5, NULL, 'restock', 1, 2, 20100.00, 'Metro Components', 'PO-1005', DATE_SUB(NOW(), INTERVAL 17 DAY)),
  (6, NULL, 'initial', 2, 2, 17700.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (6, NULL, 'restock', 1, 3, 17700.00, 'TechSource Distribution', 'PO-1006', DATE_SUB(NOW(), INTERVAL 20 DAY)),
  (7, NULL, 'initial', 1, 1, 34100.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (8, NULL, 'initial', 3, 3, 11500.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (8, NULL, 'restock', 2, 5, 11500.00, 'Metro Components', 'PO-1008', DATE_SUB(NOW(), INTERVAL 6 DAY)),
  (9, NULL, 'initial', 2, 2, 10200.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (9, NULL, 'restock', 1, 3, 10200.00, 'TechSource Distribution', 'PO-1009', DATE_SUB(NOW(), INTERVAL 9 DAY)),
  (10, NULL, 'initial', 2, 2, 16600.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (10, NULL, 'restock', 1, 3, 16600.00, 'PC Hub Wholesale', 'PO-1010', DATE_SUB(NOW(), INTERVAL 12 DAY)),
  (11, NULL, 'initial', 1, 1, 26000.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (11, NULL, 'restock', 1, 2, 26000.00, 'Metro Components', 'PO-1011', DATE_SUB(NOW(), INTERVAL 15 DAY)),
  (12, NULL, 'initial', 1, 1, 21700.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (12, NULL, 'restock', 1, 2, 21700.00, 'TechSource Distribution', 'PO-1012', DATE_SUB(NOW(), INTERVAL 18 DAY)),
  (13, NULL, 'initial', 1, 1, 28900.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (15, NULL, 'initial', 2, 2, 35700.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (15, NULL, 'restock', 1, 3, 35700.00, 'TechSource Distribution', 'PO-1015', DATE_SUB(NOW(), INTERVAL 7 DAY)),
  (16, NULL, 'initial', 1, 1, 31000.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (16, NULL, 'restock', 1, 2, 31000.00, 'PC Hub Wholesale', 'PO-1016', DATE_SUB(NOW(), INTERVAL 10 DAY)),
  (17, NULL, 'initial', 2, 2, 20800.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (17, NULL, 'restock', 2, 4, 20800.00, 'Metro Components', 'PO-1017', DATE_SUB(NOW(), INTERVAL 13 DAY)),
  (18, NULL, 'initial', 1, 1, 16600.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (19, NULL, 'initial', 1, 1, 54400.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (19, NULL, 'restock', 1, 2, 54400.00, 'PC Hub Wholesale', 'PO-1019', DATE_SUB(NOW(), INTERVAL 19 DAY)),
  (20, NULL, 'initial', 1, 1, 125000.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (21, NULL, 'initial', 1, 1, 5800.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (22, NULL, 'initial', 2, 2, 11000.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (22, NULL, 'restock', 2, 4, 11000.00, 'PC Hub Wholesale', 'PO-1022', DATE_SUB(NOW(), INTERVAL 8 DAY)),
  (23, NULL, 'initial', 2, 2, 13400.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (23, NULL, 'restock', 1, 3, 13400.00, 'Metro Components', 'PO-1023', DATE_SUB(NOW(), INTERVAL 11 DAY)),
  (24, NULL, 'initial', 1, 1, 23300.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (26, NULL, 'initial', 3, 3, 3300.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (26, NULL, 'restock', 2, 5, 3300.00, 'Metro Components', 'PO-1026', DATE_SUB(NOW(), INTERVAL 20 DAY)),
  (27, NULL, 'initial', 2, 2, 2300.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (27, NULL, 'restock', 1, 3, 2300.00, 'TechSource Distribution', 'PO-1027', DATE_SUB(NOW(), INTERVAL 3 DAY)),
  (28, NULL, 'initial', 2, 2, 3270.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (28, NULL, 'restock', 2, 4, 3270.00, 'PC Hub Wholesale', 'PO-1028', DATE_SUB(NOW(), INTERVAL 6 DAY)),
  (29, NULL, 'initial', 1, 1, 4500.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (29, NULL, 'restock', 1, 2, 4500.00, 'Metro Components', 'PO-1029', DATE_SUB(NOW(), INTERVAL 9 DAY)),
  (30, NULL, 'initial', 1, 1, 20650.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (31, NULL, 'initial', 2, 2, 18700.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (31, NULL, 'restock', 2, 4, 18700.00, 'PC Hub Wholesale', 'PO-1031', DATE_SUB(NOW(), INTERVAL 15 DAY)),
  (32, NULL, 'initial', 1, 1, 39100.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (32, NULL, 'restock', 1, 2, 39100.00, 'Metro Components', 'PO-1032', DATE_SUB(NOW(), INTERVAL 18 DAY)),
  (33, NULL, 'initial', 1, 1, 51000.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (34, NULL, 'initial', 6, 6, 1500.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (34, NULL, 'restock', 6, 12, 1500.00, 'PC Hub Wholesale', 'PO-1034', DATE_SUB(NOW(), INTERVAL 4 DAY)),
  (35, NULL, 'initial', 3, 3, 760.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (35, NULL, 'restock', 3, 6, 760.00, 'Metro Components', 'PO-1035', DATE_SUB(NOW(), INTERVAL 7 DAY)),
  (36, NULL, 'initial', 1, 1, 1700.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (36, NULL, 'restock', 1, 2, 1700.00, 'TechSource Distribution', 'PO-1036', DATE_SUB(NOW(), INTERVAL 10 DAY)),
  (37, NULL, 'initial', 2, 2, 5240.00, '', 'Opening stock count', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (37, NULL, 'restock', 1, 3, 5240.00, 'PC Hub Wholesale', 'PO-1037', DATE_SUB(NOW(), INTERVAL 13 DAY));
