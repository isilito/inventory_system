<?php
/**
 * index.php — Dashboard overview
 * Headline numbers and low-stock alerts.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$pageTitle = 'Dashboard';

// Headline totals across the whole inventory
$totals = $pdo->query(
    'SELECT COUNT(DISTINCT i.id)                       AS items,
            COALESCE(SUM(v.quantity), 0)               AS units,
            COALESCE(SUM(v.quantity * v.unit_cost), 0) AS cost_value
     FROM items i
     LEFT JOIN variants v ON v.item_id = i.id'
)->fetch();

// Variants at or below their low-stock threshold
$lowStock = $pdo->query(
    'SELECT v.*, i.name AS item_name
     FROM variants v
     JOIN items i ON i.id = v.item_id
     WHERE v.quantity <= v.low_threshold
     ORDER BY v.quantity ASC, i.name'
)->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Dashboard</h1>
    <a class="btn primary" href="item_form.php">+ New item</a>
</div>

<section class="stats">
    <div class="stat">
        <span class="stat-label">Items</span>
        <span class="stat-value"><?= (int) $totals['items'] ?></span>
    </div>
    <div class="stat">
        <span class="stat-label">Units in stock</span>
        <span class="stat-value"><?= number_format($totals['units']) ?></span>
    </div>
    <div class="stat">
        <span class="stat-label">Inventory value</span>
        <span class="stat-value"><?= money($totals['cost_value']) ?></span>
    </div>
    <div class="stat">
        <span class="stat-label">Needs restock</span>
        <span class="stat-value <?= $lowStock ? 'text-low' : '' ?>"><?= count($lowStock) ?></span>
    </div>
</section>

<section class="card">
    <h2>Low stock</h2>
    <?php if (!$lowStock): ?>
        <p class="empty">Everything is above its threshold.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Part</th><th>Stock</th><th class="num">Qty</th><th class="num">Min</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($lowStock as $v): ?>
                    <?php [$label, $class] = stock_status((int) $v['quantity'], (int) $v['low_threshold']); ?>
                    <tr>
                        <td>
                            <a href="item_view.php?id=<?= (int) $v['item_id'] ?>"><?= e($v['item_name']) ?></a>
                            <span class="muted">· <?= e($v['label']) ?></span>
                        </td>
                        <td><?= badge($label, $class) ?></td>
                        <td class="num"><?= (int) $v['quantity'] ?></td>
                        <td class="num muted"><?= (int) $v['low_threshold'] ?></td>
                        <td class="num"><a href="stock.php?variant=<?= (int) $v['id'] ?>">Restock</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
