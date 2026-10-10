<?php
/**
 * index.php — Dashboard overview
 * Headline numbers and low-stock alerts.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Dashboard';

// Add up totals and collect variants at or below their threshold
$units     = 0;
$costValue = 0.0;
$lowStock  = [];

foreach (variant_list() as $v) {
    $units     += $v['quantity'];
    $costValue += $v['quantity'] * $v['unit_cost'];

    if ($v['quantity'] <= $v['low_threshold']) {
        $lowStock[] = $v;
    }
}

// Emptiest first
usort($lowStock, fn($a, $b) => $a['quantity'] <=> $b['quantity']);

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Dashboard</h1>
    <a class="btn primary" href="item_form.php">+ New item</a>
</div>

<section class="stats">
    <div class="stat">
        <span class="stat-label">Items</span>
        <span class="stat-value"><?= count(rows('items')) ?></span>
    </div>
    <div class="stat">
        <span class="stat-label">Units in stock</span>
        <span class="stat-value"><?= number_format($units) ?></span>
    </div>
    <div class="stat">
        <span class="stat-label">Inventory value</span>
        <span class="stat-value"><?= money($costValue) ?></span>
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
                    <?php [$label, $class] = stock_status($v['quantity'], $v['low_threshold']); ?>
                    <tr>
                        <td>
                            <a href="item_view.php?id=<?= $v['item_id'] ?>"><?= e($v['item_name']) ?></a>
                            <span class="muted">· <?= e($v['label']) ?></span>
                        </td>
                        <td><?= badge($label, $class) ?></td>
                        <td class="num"><?= $v['quantity'] ?></td>
                        <td class="num muted"><?= $v['low_threshold'] ?></td>
                        <td class="num"><a href="stock.php?variant=<?= $v['id'] ?>">Restock</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
