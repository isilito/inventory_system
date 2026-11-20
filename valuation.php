<?php
/**
 * valuation.php — Inventory valuation
 * Stock value at cost and at retail, broken down by category,
 * plus the most valuable parts on the shelf.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle = 'Valuation';

// Value per category — MySQL does the adding up with SUM() and GROUP BY
$rows = db_rows(
    "SELECT COALESCE(c.name, 'Uncategorized') AS category,
            COUNT(v.id) AS variants,
            SUM(v.quantity) AS units,
            SUM(v.quantity * v.unit_cost)  AS cost_value,
            SUM(v.quantity * v.sell_price) AS retail_value
     FROM variants v
     JOIN items i ON i.id = v.item_id
     LEFT JOIN categories c ON c.id = i.category_id
     GROUP BY COALESCE(c.name, 'Uncategorized')
     ORDER BY cost_value DESC"
);

// Grand totals
$grand = ['units' => 0, 'cost' => 0.0, 'retail' => 0.0];
foreach ($rows as $r) {
    $grand['units']  += $r['units'];
    $grand['cost']   += $r['cost_value'];
    $grand['retail'] += $r['retail_value'];
}
$grandProfit = $grand['retail'] - $grand['cost'];

// The 10 most valuable in-stock variants
$topParts = db_rows(
    'SELECT v.*, i.name AS item_name, i.image, v.quantity * v.unit_cost AS value
     FROM variants v
     JOIN items i ON i.id = v.item_id
     WHERE v.quantity > 0
     ORDER BY value DESC
     LIMIT 10'
);

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Inventory valuation</h1>
    <button class="btn no-print" onclick="window.print()">Print</button>
</div>

<section class="stats">
    <div class="stat"><span class="stat-label">Units on hand</span><span class="stat-value"><?= number_format($grand['units']) ?></span></div>
    <div class="stat"><span class="stat-label">Value at cost</span><span class="stat-value"><?= money($grand['cost']) ?></span></div>
    <div class="stat"><span class="stat-label">Value at retail</span><span class="stat-value"><?= money($grand['retail']) ?></span></div>
    <div class="stat">
        <span class="stat-label">Potential profit</span>
        <span class="stat-value"><?= money($grandProfit) ?></span>
        <span class="stat-sub"><?= $grand['retail'] > 0 ? number_format($grandProfit / $grand['retail'] * 100, 1) : '0.0' ?>% margin</span>
    </div>
</section>

<section class="card">
    <h2>By category</h2>
    <?php if (!$rows): ?>
        <p class="empty">No stock to value yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Category</th><th class="num">Variants</th><th class="num">Units</th><th class="num">At cost</th><th class="num">At retail</th><th>Share of value</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $share = $grand['cost'] > 0 ? $r['cost_value'] / $grand['cost'] * 100 : 0; ?>
                    <tr>
                        <td><?= e($r['category']) ?></td>
                        <td class="num"><?= (int) $r['variants'] ?></td>
                        <td class="num"><?= (int) $r['units'] ?></td>
                        <td class="num"><?= money($r['cost_value']) ?></td>
                        <td class="num"><?= money($r['retail_value']) ?></td>
                        <td>
                            <div class="bar"><span style="width: <?= round($share, 1) ?>%"></span></div>
                            <span class="muted small"><?= number_format($share, 1) ?>%</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr>
                    <th>Total</th><th></th>
                    <th class="num"><?= number_format($grand['units']) ?></th>
                    <th class="num"><?= money($grand['cost']) ?></th>
                    <th class="num"><?= money($grand['retail']) ?></th>
                    <th></th>
                </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Most valuable stock</h2>
    <?php if (!$topParts): ?>
        <p class="empty">Nothing in stock.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Part</th><th class="num">Qty</th><th class="num">Avg. cost</th><th class="num">Value</th></tr></thead>
                <tbody>
                <?php foreach ($topParts as $p): ?>
                    <tr>
                        <td>
                            <div class="part-cell">
                                <img class="thumb" src="<?= e(image_url('products', $p['image'])) ?>" alt="">
                                <span>
                                    <a href="item_view.php?id=<?= (int) $p['item_id'] ?>"><?= e($p['item_name']) ?></a>
                                    <span class="muted">· <?= e($p['label']) ?></span>
                                </span>
                            </div>
                        </td>
                        <td class="num"><?= (int) $p['quantity'] ?></td>
                        <td class="num"><?= money($p['unit_cost']) ?></td>
                        <td class="num"><?= money($p['value']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
