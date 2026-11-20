<?php
/**
 * seed.php
 * Sample data loaded the first time the app runs: a demo account,
 * categories, products with pictures, variants and stock history.
 */

function seed_sample_data(): void
{
    // ---------- Demo account (password is stored as a hash) ----------
    db_run(
        'INSERT INTO users (full_name, username, email, password) VALUES (?, ?, ?, ?)',
        'ssss',
        ['Shop Admin', 'admin', 'admin@partsbin.local', password_hash('admin123', PASSWORD_DEFAULT)]
    );

    // ---------- Categories ----------
    $categories = [
        'Processors'     => 'Desktop CPUs',
        'Graphics Cards' => 'Dedicated GPUs',
        'Memory'         => 'Desktop RAM kits',
        'Storage'        => 'SSDs and NAS hard drives',
        'Power Supplies' => 'ATX PSUs',
        'Monitors'       => 'Office, creator and gaming displays',
        'Peripherals'    => 'Mice, keyboards and webcams',
    ];

    $categoryIds = [];
    foreach ($categories as $name => $description) {
        db_run('INSERT INTO categories (name, description) VALUES (?, ?)', 'ss', [$name, $description]);
        $categoryIds[$name] = db_insert_id();
    }

    // ---------- Products ----------
    // Product names and prices are based on the PC Express (pcx.com.ph) catalogue;
    // photos are official manufacturer images (see assets/samples/SOURCES.md).
    // Related listings are grouped as variants of one product.
    // [category, name, brand, status tag, sample picture, [[label, sku, qty, cost, price, threshold], ...]]
    $items = [
        ['Processors', 'Core Ultra 5 Desktop Processor', 'Intel', 'New', 'cpu-ultra5.jpg', [
            ['225 (up to 4.9GHz)', 'CPU-INT-U5-225', 4, 10100, 11900, 2],
            ['225F (no iGPU)', 'CPU-INT-U5-225F', 3, 10150, 11950, 2],
            ['245K Unlocked', 'CPU-INT-U5-245K', 2, 12400, 14600, 2],
            ['245KF Unlocked (no iGPU)', 'CPU-INT-U5-245KF', 0, 11550, 13600, 1],
        ]],
        ['Processors', 'Core Ultra 7 Desktop Processor', 'Intel', 'New', 'cpu-ultra7.jpg', [
            ['265', 'CPU-INT-U7-265', 2, 20100, 23650, 1],
            ['265K Unlocked', 'CPU-INT-U7-265K', 3, 17700, 20850, 1],
        ]],
        ['Processors', 'Core Ultra 9 285K Desktop Processor', 'Intel', 'New', 'cpu-ultra9.jpg', [
            ['Boxed', 'CPU-INT-U9-285K', 1, 34100, 40150, 1],
        ]],
        ['Processors', 'Ryzen 5 9000 Series Desktop Processor', 'AMD', 'New', 'amd-ryzen5.jpg', [
            ['9600X (6 cores, up to 5.4GHz)', 'CPU-AMD-R5-9600X', 5, 11500, 13500, 2],
            ['9600 (6 cores, up to 5.2GHz)', 'CPU-AMD-R5-9600', 3, 10200, 12000, 2],
        ]],
        ['Processors', 'Ryzen 7 9000 Series Desktop Processor', 'AMD', 'New', 'amd-ryzen7.jpg', [
            ['9700X (8 cores, up to 5.5GHz)', 'CPU-AMD-R7-9700X', 3, 16600, 19500, 2],
            ['9800X3D (8 cores, 3D V-Cache)', 'CPU-AMD-R7-9800X3D', 2, 26000, 30500, 2],
        ]],
        ['Processors', 'Ryzen 9 9000 Series Desktop Processor', 'AMD', 'New', 'amd-ryzen9.jpg', [
            ['9900X (12 cores)', 'CPU-AMD-R9-9900X', 2, 21700, 25500, 1],
            ['9950X (16 cores)', 'CPU-AMD-R9-9950X', 1, 28900, 34000, 1],
            ['9950X3D (16 cores, 3D V-Cache)', 'CPU-AMD-R9-9950X3D', 0, 37400, 44000, 1],
        ]],
        ['Graphics Cards', 'Radeon RX 9070 Series Graphics Card', 'AMD', 'New', 'amd-rx9070.jpg', [
            ['RX 9070 XT 16GB GDDR6', 'GPU-AMD-RX9070XT', 3, 35700, 42000, 1],
            ['RX 9070 16GB GDDR6', 'GPU-AMD-RX9070', 2, 31000, 36500, 1],
        ]],
        ['Graphics Cards', 'Radeon RX 9060 XT Graphics Card', 'AMD', 'New', 'amd-rx9060.jpg', [
            ['16GB GDDR6', 'GPU-AMD-RX9060XT-16', 4, 20800, 24500, 2],
            ['8GB GDDR6', 'GPU-AMD-RX9060XT-8', 1, 16600, 19500, 2],
        ]],
        ['Graphics Cards', 'GeForce RTX 5070 Ventus 2X OC 12GB', 'MSI', 'New', 'gpu-5070.jpg', [
            ['White (G5070-12V2CW)', 'GPU-MSI-5070-V2XW', 2, 54400, 64000, 1],
        ]],
        ['Graphics Cards', 'GeForce RTX 5080 Ventus 3X OC 16GB', 'MSI', 'New', 'gpu-5080.jpg', [
            ['G5080-16V3C', 'GPU-MSI-5080-V3X', 1, 125000, 147000, 1],
        ]],
        ['Memory', 'HT100 DDR4 Desktop Memory', 'Acer', 'Discontinued', 'ram-acer.jpg', [
            ['16GB 3200MHz', 'RAM-ACER-HT100-16', 1, 5800, 6800, 2],
        ]],
        ['Storage', 'IronWolf NAS Hard Drive', 'Seagate', 'New', 'hdd-ironwolf.jpg', [
            ['2TB (ST2000VN004)', 'HDD-IW-2T', 4, 11000, 12950, 2],
            ['4TB (ST4000VN006)', 'HDD-IW-4T', 3, 13400, 15800, 2],
            ['8TB 7200RPM', 'HDD-IW-8T', 1, 23300, 27400, 1],
            ['12TB (ST12000VN0008)', 'HDD-IW-12T', 0, 33100, 38900, 1],
        ]],
        ['Power Supplies', 'MAG A750BN 80+ Bronze Power Supply', 'MSI', 'New', 'psu-a750bn.jpg', [
            ['750W Semi-Modular', 'PSU-MSI-A750BN', 5, 3300, 3900, 2],
        ]],
        ['Monitors', 'IPS Monitor', 'AOpen', 'New', 'mon-aopen.jpg', [
            ['19.5" 20E0Q BI 75Hz', 'MN-AOP-20E0Q', 3, 2300, 2700, 1],
            ['23.8" 24CL1Y GBI', 'MN-AOP-24CL1Y', 4, 3270, 3850, 2],
            ['27" 27SA3 G0BI', 'MN-AOP-27SA3', 2, 4500, 5300, 1],
        ]],
        ['Monitors', 'ProArt Display PA278CV 27" Monitor', 'ASUS', 'New', 'mon-proart.jpg', [
            ['27" WQHD', 'MN-ASUS-PA278CV', 1, 20650, 24300, 1],
        ]],
        ['Monitors', 'ROG Strix XG27ACMES 27" Gaming Monitor', 'ASUS', 'New', 'asus-xg27acmes.jpg', [
            ['27" 1440p 255Hz Fast IPS', 'MN-ASUS-XG27ACMES', 4, 18700, 22000, 2],
        ]],
        ['Monitors', 'ROG Swift OLED PG27AQDM 27" Gaming Monitor', 'ASUS', 'New', 'asus-pg27aqdm.jpg', [
            ['27" 1440p 240Hz OLED', 'MN-ASUS-PG27AQDM', 2, 39100, 46000, 1],
        ]],
        ['Monitors', 'ROG Swift OLED PG27AQDP 27" Gaming Monitor', 'ASUS', 'New', 'asus-pg27aqdp.jpg', [
            ['27" 1440p 480Hz OLED', 'MN-ASUS-PG27AQDP', 1, 51000, 60000, 1],
        ]],
        ['Peripherals', 'G304 Lightspeed Wireless Gaming Mouse', 'Logitech', 'New', 'mouse-g304.jpg', [
            ['Black', 'MS-LOGI-G304', 12, 1500, 1780, 4],
        ]],
        ['Peripherals', 'TUF Gaming M3 Gen II Gaming Mouse', 'ASUS', 'New', 'mouse-m3.jpg', [
            ['P309 RGB USB Wired', 'MS-ASUS-M3G2', 6, 760, 900, 3],
        ]],
        ['Peripherals', 'TUF Gaming K1 RGB Gaming Keyboard', 'ASUS', 'New', 'kb-tufk1.jpg', [
            ['US layout', 'KB-ASUS-TUFK1', 2, 1700, 2000, 2],
        ]],
        ['Peripherals', 'C922 Pro HD Stream Webcam', 'Logitech', 'Used', 'cam-c922.jpg', [
            ['1080p', 'CAM-LOGI-C922', 3, 5240, 6160, 2],
        ]],
    ];

    $suppliers = ['TechSource Distribution', 'PC Hub Wholesale', 'Metro Components'];

    // Make sure uploads/products/ exists before copying pictures into it
    $productDir = UPLOAD_DIR . 'products/';
    if (!is_dir($productDir)) {
        mkdir($productDir, 0777, true);
    }

    $count = 0;
    foreach ($items as $item) {
        $count++;
        list($category, $name, $brand, $tag, $sample, $variants) = $item;

        // Copy the sample picture into uploads/products/ like a real upload
        $image  = 'products_sample_' . $count . '_' . $sample;
        $source = __DIR__ . '/../assets/samples/' . $sample;
        if (file_exists($source)) {
            copy($source, $productDir . $image);
        } else {
            $image = null;
        }

        db_run(
            'INSERT INTO items (category_id, name, brand, status, notes, image, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            'issssss',
            [$categoryIds[$category], $name, $brand, $tag, '', $image, days_ago(30 + $count)]
        );
        $itemId = db_insert_id();

        foreach ($variants as $v) {
            list($label, $sku, $qty, $cost, $price, $threshold) = $v;

            db_run(
                'INSERT INTO variants (item_id, label, sku, quantity, unit_cost, sell_price, low_threshold) VALUES (?, ?, ?, 0, ?, ?, ?)',
                'issddi',
                [$itemId, $label, $sku, $cost, $price, $threshold]
            );
            $variantId = db_insert_id();

            // Split stock into an opening count + a later restock so history has data
            $restockQty = intdiv($qty, 2);
            $openingQty = $qty - $restockQty;

            if ($openingQty > 0) {
                change_stock($variantId, $openingQty, 'initial', 0, '', 'Opening stock count', days_ago(30));
            }
            if ($restockQty > 0) {
                change_stock($variantId, $restockQty, 'restock', $cost, $suppliers[$variantId % 3], 'PO-' . (1000 + $variantId), days_ago(($variantId * 3) % 20 + 2));
            }
        }
    }
}

// Timestamp string for N days in the past
function days_ago(int $days): string
{
    return date('Y-m-d H:i:s', strtotime("-$days days"));
}
