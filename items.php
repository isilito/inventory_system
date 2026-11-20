<?php
/**
 * items.php — Product list
 * Search and filter products by category, status tag and stock level.
 * Shows each product's picture, its variants and total stock.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle = 'Products';

// Filters come from the query string ($_GET), e.g. items.php?q=ryzen&category=1
$search     = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category'] ?? 0);
$tag        = $_GET['tag'] ?? '';
$stockLevel = $_GET['stock'] ?? '';

// Build the WHERE part of the query from the filters that are filled in
$where  = [];
$types  = '';
$params = [];

if ($search !== '') {
    // Match the name, brand, or any of the product's variant labels / SKUs
    $where[] = '(i.name LIKE ? OR i.brand LIKE ? OR EXISTS (
                    SELECT 1 FROM variants sv WHERE sv.item_id = i.id AND (sv.label LIKE ? OR sv.sku LIKE ?)))';
    $like = '%' . $search . '%';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}
if ($categoryId > 0) {
    $where[] = 'i.category_id = ?';
    $types  .= 'i';
    $params[] = $categoryId;
}
if ($tag !== '') {
    $where[] = 'i.status = ?';
    $types  .= 's';
    $params[] = $tag;
}

$sql = 'SELECT i.*, c.name AS category,
               COUNT(v.id) AS variant_count,
               COALESCE(SUM(v.quantity), 0) AS total_qty,
               COALESCE(SUM(v.quantity <= 0), 0) AS out_count,
               COALESCE(SUM(v.quantity > 0 AND v.quantity <= v.low_threshold), 0) AS low_count,
               GROUP_CONCAT(v.label ORDER BY v.id SEPARATOR " · ") AS variant_labels
        FROM items i
        LEFT JOIN categories c ON c.id = i.category_id
        LEFT JOIN variants v   ON v.item_id = i.id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' GROUP BY i.id, i.category_id, i.name, i.brand, i.status, i.notes, i.image, i.created_at, c.name';

// Stock-level filter works on the totals, so it goes in HAVING
if ($stockLevel === 'low') {
    $sql .= ' HAVING low_count > 0';
} elseif ($stockLevel === 'out') {
    $sql .= ' HAVING out_count > 0';
}
$sql .= ' ORDER BY c.name IS NULL, c.name, i.name';

$items      = db_rows($sql, $types, $params);
$categories = all_categories();
$isFiltered = $search !== '' || $categoryId > 0 || $tag !== '' || $stockLevel !== '';

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Products</h1>
    <a class="btn primary" href="item_form.php">+ New product</a>
</div>

<form method="get" class="filters">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, brand or SKU">
    <select name="category">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
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
        <p class="empty"><?= $isFiltered ? 'No products match these filters.' : 'No products yet. Add your first part.' ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Product</th><th>Category</th><th>Variants</th><th>Tag</th><th>Stock</th><th class="num">Qty</th></tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                    // Product-level stock badge: worst status among its variants
                    if ($item['variant_count'] == 0) {
                        $stock = ['No variants', 'muted'];
                    } elseif ($item['total_qty'] <= 0) {
                        $stock = ['Out of stock', 'out'];
                    } elseif ($item['low_count'] > 0 || $item['out_count'] > 0) {
                        $stock = ['Low stock', 'low'];
                    } else {
                        $stock = ['In stock', 'ok'];
                    }
                    ?>
                    <tr>
                        <td>
                            <div class="part-cell">
                                <img class="thumb" src="<?= e(image_url('products', $item['image'])) ?>" alt="">
                                <div>
                                    <a href="item_view.php?id=<?= (int) $item['id'] ?>"><strong><?= e($item['name']) ?></strong></a>
                                    <div class="muted small"><?= e($item['brand']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="muted"><?= e($item['category'] ?? 'Uncategorized') ?></td>
                        <td class="small">
                            <?= (int) $item['variant_count'] ?>
                            <span class="muted">— <?= e($item['variant_labels'] ?? 'none') ?></span>
                        </td>
                        <td><?= badge($item['status'], tag_class($item['status'])) ?></td>
                        <td><?= badge($stock[0], $stock[1]) ?></td>
                        <td class="num"><?= (int) $item['total_qty'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted small"><?= count($items) ?> product(s)</p>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
