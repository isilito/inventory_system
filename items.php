<?php
/**
 * items.php — Item list
 * Search and filter items by category, status tag and stock level.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$pageTitle = 'Items';

// Filters come from the query string
$search     = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category'] ?? 0);
$tag        = $_GET['tag'] ?? '';
$stockLevel = $_GET['stock'] ?? '';

// Build the WHERE clause from whichever filters are set
$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(i.name LIKE ? OR i.brand LIKE ? OR EXISTS (SELECT 1 FROM variants s WHERE s.item_id = i.id AND (s.sku LIKE ? OR s.label LIKE ?)))';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like, $like]);
}
if ($categoryId > 0) {
    $where[]  = 'i.category_id = ?';
    $params[] = $categoryId;
}
if (in_array($tag, STATUS_TAGS, true)) {
    $where[]  = 'i.status = ?';
    $params[] = $tag;
}

// Stock filters work on the aggregated values, so they go in HAVING
$having = match ($stockLevel) {
    'low'   => 'HAVING low_count > 0',
    'out'   => 'HAVING out_count > 0',
    default => '',
};

$sql = 'SELECT i.*, c.name AS category,
               COUNT(v.id)                                AS variant_count,
               COALESCE(SUM(v.quantity), 0)               AS total_qty,
               COALESCE(SUM(v.quantity * v.unit_cost), 0) AS value,
               COALESCE(SUM(CASE WHEN v.quantity <= 0 THEN 1 ELSE 0 END), 0) AS out_count,
               COALESCE(SUM(CASE WHEN v.quantity > 0 AND v.quantity <= v.low_threshold THEN 1 ELSE 0 END), 0) AS low_count
        FROM items i
        LEFT JOIN categories c ON c.id = i.category_id
        LEFT JOIN variants v   ON v.item_id = i.id '
     . ($where ? 'WHERE ' . implode(' AND ', $where) : '')
     . ' GROUP BY i.id ' . $having
     . ' ORDER BY c.name, i.name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
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
            <option value="<?= (int) $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
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
                <tr>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Tag</th>
                    <th>Stock</th>
                    <th class="num">Qty</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                    // Item-level stock badge: worst status among its variants
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
                            <a href="item_view.php?id=<?= (int) $item['id'] ?>"><?= e($item['name']) ?></a>
                            <span class="muted"><?= e($item['brand']) ?></span>
                        </td>
                        <td class="muted"><?= e($item['category'] ?? 'Uncategorized') ?></td>
                        <td><?= badge($item['status'], tag_class($item['status'])) ?></td>
                        <td><?= badge($stock[0], $stock[1]) ?></td>
                        <td class="num"><?= (int) $item['total_qty'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted small"><?= count($items) ?> item(s)</p>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
