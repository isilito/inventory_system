<?php
/**
 * db.php
 * Opens the SQLite connection. On the very first run it also builds
 * the tables and fills them with sample data.
 */

require_once __DIR__ . '/../config.php';

/**
 * Returns the shared PDO connection (one per request).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $folder = dirname(DB_PATH);
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        $isFirstRun = !file_exists(DB_PATH);

        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        if ($isFirstRun) {
            try {
                $pdo->beginTransaction();
                create_schema($pdo);
                seed_sample_data();
                $pdo->commit();
            } catch (Throwable $ex) {
                // Don't leave a half-built database behind
                $pdo->rollBack();
                $pdo = null;
                @unlink(DB_PATH);
                throw $ex;
            }
        }
    }

    return $pdo;
}

/**
 * Creates all tables.
 */
function create_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE categories (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT NOT NULL UNIQUE,
            description TEXT NOT NULL DEFAULT ''
        );

        CREATE TABLE items (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
            name        TEXT NOT NULL,
            brand       TEXT NOT NULL DEFAULT '',
            status      TEXT NOT NULL DEFAULT 'New',
            notes       TEXT NOT NULL DEFAULT '',
            created_at  TEXT NOT NULL
        );

        -- Each item has one or more variants (size, capacity, color...).
        -- Stock, cost and price live on the variant.
        CREATE TABLE variants (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id       INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
            label         TEXT NOT NULL,
            sku           TEXT NOT NULL DEFAULT '',
            quantity      INTEGER NOT NULL DEFAULT 0,
            unit_cost     REAL NOT NULL DEFAULT 0,
            sell_price    REAL NOT NULL DEFAULT 0,
            low_threshold INTEGER NOT NULL DEFAULT 2
        );

        -- Every change to stock is logged here (opening counts, restocks, adjustments).
        CREATE TABLE stock_movements (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            variant_id INTEGER NOT NULL REFERENCES variants(id) ON DELETE CASCADE,
            type       TEXT NOT NULL,
            qty_change INTEGER NOT NULL,
            qty_after  INTEGER NOT NULL,
            unit_cost  REAL,
            supplier   TEXT NOT NULL DEFAULT '',
            reason     TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL
        );
    ");
}

/**
 * Fills the database with a realistic starting inventory.
 * Uses the same helper functions as the app so the history is consistent.
 */
function seed_sample_data(): void
{
    $pdo = db();

    $categories = [
        'Processors'      => 'Desktop CPUs',
        'Graphics Cards'  => 'Dedicated GPUs',
        'Memory'          => 'Desktop RAM kits',
        'Storage'         => 'SSDs and hard drives',
        'Motherboards'    => 'AM5 / LGA1700 boards',
        'Power Supplies'  => 'ATX PSUs',
        'Cases'           => 'Chassis and enclosures',
        'Cooling'         => 'Coolers, fans and thermal paste',
        'Cables & Parts'  => 'Cables, adapters and replacement parts',
    ];

    $categoryIds = [];
    $insertCategory = $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
    foreach ($categories as $name => $description) {
        $insertCategory->execute([$name, $description]);
        $categoryIds[$name] = (int) $pdo->lastInsertId();
    }

    // [category, item name, brand, status tag, [[variant label, sku, qty, cost, price, threshold], ...]]
    $items = [
        ['Processors', 'Ryzen 5 7600', 'AMD', 'New', [
            ['Boxed', 'CPU-R5-7600', 6, 185, 229, 2],
        ]],
        ['Processors', 'Core i5-12400F', 'Intel', 'New', [
            ['Boxed', 'CPU-I5-12400F', 2, 110, 145, 2],
        ]],
        ['Graphics Cards', 'GeForce RTX 4060', 'MSI', 'New', [
            ['Ventus 2X 8GB', 'GPU-4060-V2X', 3, 285, 329, 1],
            ['Gaming X 8GB', 'GPU-4060-GX', 0, 310, 359, 1],
        ]],
        ['Graphics Cards', 'Radeon RX 6600', 'Sapphire', 'Refurbished', [
            ['Pulse 8GB', 'GPU-6600-P', 2, 150, 199, 1],
        ]],
        ['Memory', 'Vengeance LPX DDR4', 'Corsair', 'New', [
            ['8GB 3200MHz', 'RAM-D4-8-3200', 12, 18, 29, 4],
            ['16GB (2x8) 3200MHz', 'RAM-D4-16-3200', 5, 35, 52, 3],
            ['32GB (2x16) 3600MHz', 'RAM-D4-32-3600', 1, 68, 95, 2],
        ]],
        ['Memory', 'Fury Beast DDR5', 'Kingston', 'New', [
            ['32GB (2x16) 6000MHz', 'RAM-D5-32-6000', 4, 95, 129, 2],
        ]],
        ['Storage', '980 NVMe SSD', 'Samsung', 'New', [
            ['500GB', 'SSD-980-500', 7, 40, 59, 3],
            ['1TB', 'SSD-980-1T', 4, 65, 89, 2],
        ]],
        ['Storage', 'WD Blue HDD', 'Western Digital', 'Used', [
            ['1TB 7200rpm', 'HDD-WD-1T', 4, 15, 28, 2],
        ]],
        ['Motherboards', 'B650M DS3H', 'Gigabyte', 'New', [
            ['Micro-ATX', 'MB-B650M-DS3H', 2, 120, 149, 1],
        ]],
        ['Power Supplies', 'RM e-Series', 'Corsair', 'New', [
            ['650W 80+ Gold', 'PSU-RM650E', 3, 75, 99, 2],
            ['750W 80+ Gold', 'PSU-RM750E', 1, 90, 115, 2],
        ]],
        ['Cases', 'MasterBox NR200P', 'Cooler Master', 'New', [
            ['Black', 'CASE-NR200P-B', 2, 80, 105, 1],
            ['White', 'CASE-NR200P-W', 0, 80, 105, 1],
        ]],
        ['Cooling', 'MX-4 Thermal Paste', 'Arctic', 'New', [
            ['4g tube', 'TP-MX4-4G', 15, 5, 9, 5],
        ]],
        ['Cooling', 'AK400 Air Cooler', 'DeepCool', 'New', [
            ['Black', 'COOL-AK400', 4, 25, 35, 2],
        ]],
        ['Cables & Parts', 'SATA III Data Cable', 'Generic', 'New', [
            ['50cm', 'CBL-SATA-50', 25, 0.80, 3, 10],
        ]],
        ['Cables & Parts', 'Laptop Keyboard', 'Generic', 'Defective', [
            ['Lenovo IdeaPad 3', 'KB-IP3', 1, 12, 30, 1],
        ]],
    ];

    $suppliers = ['TechSource Distribution', 'PC Hub Wholesale', 'Metro Components'];

    $insertItem = $pdo->prepare(
        'INSERT INTO items (category_id, name, brand, status, created_at) VALUES (?, ?, ?, ?, ?)'
    );
    $insertVariant = $pdo->prepare(
        'INSERT INTO variants (item_id, label, sku, quantity, unit_cost, sell_price, low_threshold)
         VALUES (?, ?, ?, 0, ?, ?, ?)'
    );

    foreach ($items as [$category, $name, $brand, $tag, $variants]) {
        $insertItem->execute([$categoryIds[$category], $name, $brand, $tag, days_ago(45)]);
        $itemId = (int) $pdo->lastInsertId();

        foreach ($variants as [$label, $sku, $qty, $cost, $price, $threshold]) {
            $insertVariant->execute([$itemId, $label, $sku, $cost, $price, $threshold]);
            $variantId = (int) $pdo->lastInsertId();

            // Split stock into an opening count + a later restock so history has data
            $restockQty = intdiv($qty, 2);
            $openingQty = $qty - $restockQty;

            if ($openingQty > 0) {
                change_stock($variantId, $openingQty, 'initial', [
                    'reason'     => 'Opening stock count',
                    'created_at' => days_ago(30),
                ]);
            }
            if ($restockQty > 0) {
                change_stock($variantId, $restockQty, 'restock', [
                    'unit_cost'  => $cost,
                    'supplier'   => $suppliers[$variantId % 3],
                    'reason'     => 'PO-' . (1000 + $variantId),
                    'created_at' => days_ago(($variantId * 3) % 20 + 2),
                ]);
            }
        }
    }
}

/**
 * Timestamp string for N days in the past (used by the sample data).
 */
function days_ago(int $days): string
{
    return date('Y-m-d H:i:s', strtotime("-{$days} days"));
}
