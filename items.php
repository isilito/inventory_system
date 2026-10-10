<?php
/**
 * items.php - Item list
 * Search and filter items by category, status tag and stock level.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Items';

// Filters come from the query string
$search     = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category'] ?? 0);
$tag        = $_GET['tag'] ?? '';
$stockLevel = $_GET['stock'] ?? '';

$items = [];

foreach (rows('items') as $item) {
    $variants = item_variants($item['id']);

    // Search matches name, brand, or any variant's label / SKU
    if ($search !== '') {
        $haystack = $item['name'] . ' ' . $item['brand'];
        foreach ($variants as $v) {
            $haystack .= ' ' . $v['label'] . ' ' . $v['sku'];
        }
        if (stripos($haystack, $search) === false) {
            continue;
        }
    }
    if ($categoryId > 0 && $item['category_id'] !== $categoryId) {
        continue;
    }
    if ($tag !== '' && $item['status'] !== $tag) {
        continue;
    }

    // "Out" (0 or less) and "low" (at or below threshold) are counted separately
    $totalQty = 0;
    $lowCount = 0;
    $outCount = 0;
    foreach ($variants as $v) {
        $totalQty += $v['quantity'];
        if ($v['quantity'] <= 0) {
            $outCount++;
        } elseif ($v['quantity'] <= $v['low_threshold']) {
            $lowCount++;
        }
    }

    // An item matches if at least one of its variants is low / out
    $keep = match ($stockLevel) {
        'low'   => $lowCount > 0,
        'out'   => $outCount > 0,
        default => true,
    };
    if (!$keep) {
        continue;
    }

    // Item-level badge reflects the worst case among its variants
    if (!$variants) {
        $stock = ['No variants', 'muted'];
    } elseif ($totalQty <= 0) {
        $stock = ['Out of stock', 'out'];
    } elseif ($lowCount > 0 || $outCount > 0) {
        $stock = ['Low stock', 'low'];
    } else {
        $stock = ['In stock', 'ok'];
    }

    $item['category']  = category_name($item['category_id']);
    $item['total_qty'] = $totalQty;
    $item['stock']     = $stock;
    $items[] = $item;
}

// Sort by category, then name
usort($items, fn($a, $b) => [$a['category'], $a['name']] <=> [$b['category'], $b['name']]);

$categories = rows('categories');
usort($categories, fn($a, $b) => strcasecmp($a['name'], $b['name']));

// Controls the empty-state message and the "Clear" button
$isFiltered = $search !== '' || $categoryId > 0 || $tag !== '' || $stockLevel !== '';

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Items</h1>
    <a class="btn primary" href="item_form.php">+ New item</a>
</div>

<form method="get" class="filters">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, brand or SKU">
    <select name="category">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $categoryId === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="tag">
        <option value="">All tags</option>
        <?php foreach (STATUS_TAGS as $t): ?>
            <option <?= $tag === $t ? 'selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="stock">
        <option value="">Any stock</option>
        <option value="low" <?= $stockLevel === 'low' ? 'selected' : '' ?>>Low stock</option>
        <option value="out" <?= $stockLevel === 'out' ? 'selected' : '' ?>>Out of stock</option>
    </select>
    <button class="btn">Filter</button>
    <?php if ($isFiltered): ?><a class="btn ghost" href="items.php">Clear</a><?php endif; ?>
</form>

<section class="card">
    <?php if (!$items): ?>
        <p class="empty"><?= $isFiltered ? 'No items match these filters.' : 'No items yet. Add your first part.' ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Item</th><th>Category</th><th>Tag</th><th>Stock</th><th class="num">Qty</th></tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <a href="item_view.php?id=<?= $item['id'] ?>"><?= e($item['name']) ?></a>
                            <span class="muted"><?= e($item['brand']) ?></span>
                        </td>
                        <td class="muted"><?= e($item['category']) ?></td>
                        <td><?= badge($item['status'], tag_class($item['status'])) ?></td>
                        <td><?= badge($item['stock'][0], $item['stock'][1]) ?></td>
                        <td class="num"><?= $item['total_qty'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted small"><?= count($items) ?> item(s)</p>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>