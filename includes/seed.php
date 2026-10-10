<?php
/**
 * seed.php
 * Sample inventory used the first time the app runs.
 * Uses the same helper functions as the app so the stock history is consistent.
 */

require_once __DIR__ . '/functions.php';

function seed_sample_data(): void
{
    $categories = [
        'Processors'     => 'Desktop CPUs',
        'Graphics Cards' => 'Dedicated GPUs',
        'Memory'         => 'Desktop RAM kits',
        'Storage'        => 'SSDs and hard drives',
        'Motherboards'   => 'AM5 / LGA1700 boards',
        'Power Supplies' => 'ATX PSUs',
        'Cases'          => 'Chassis and enclosures',
        'Cooling'        => 'Coolers, fans and thermal paste',
        'Cables & Parts' => 'Cables, adapters and replacement parts',
    ];

    $categoryIds = [];
    foreach ($categories as $name => $description) {
        $categoryIds[$name] = insert_row('categories', ['name' => $name, 'description' => $description]);
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

    foreach ($items as [$category, $name, $brand, $tag, $variants]) {
        $itemId = insert_row('items', [
            'category_id' => $categoryIds[$category],
            'name'        => $name,
            'brand'       => $brand,
            'status'      => $tag,
            'notes'       => '',
            'created_at'  => days_ago(45),
        ]);

        foreach ($variants as [$label, $sku, $qty, $cost, $price, $threshold]) {
            $variantId = insert_row('variants', [
                'item_id'       => $itemId,
                'label'         => $label,
                'sku'           => $sku,
                'quantity'      => 0,
                'unit_cost'     => (float) $cost,
                'sell_price'    => (float) $price,
                'low_threshold' => $threshold,
            ]);

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
                    'unit_cost'  => (float) $cost,
                    'supplier'   => $suppliers[$variantId % 3],
                    'reason'     => 'PO-' . (1000 + $variantId),
                    'created_at' => days_ago(($variantId * 3) % 20 + 2),
                ]);
            }
        }
    }
}

// Timestamp string for N days in the past
function days_ago(int $days): string
{
    return date('Y-m-d H:i:s', strtotime("-{$days} days"));
}
