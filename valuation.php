<?php
/**
 * valuation.php — Inventory valuation
 * Stock value at cost and at retail, broken down by category,
 * plus the most valuable parts on the shelf.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Valuation';

$variants = variant_list();

// Group variants by category and add up their value
$rows  = [];
$grand = ['units' => 0, 'cost' => 0.0, 'retail' => 0.0];

foreach ($variants as $v) {
    $category = category_name($v['category_id']);

    $rows[$category] ??= ['category' => $category, 'variants' => 0, 'units' => 0, 'cost_value' => 0.0, 'retail_value' => 0.0];
    $rows[$category]['variants']++;
    $rows[$category]['units']        += $v['quantity'];
    $rows[$category]['cost_value']   += $v['quantity'] * $v['unit_cost'];
    $rows[$category]['retail_value'] += $v['quantity'] * $v['sell_price'];

    $grand['units']  += $v['quantity'];
    $grand['cost']   += $v['quantity'] * $v['unit_cost'];
    $grand['retail'] += $v['quantity'] * $v['sell_price'];
}
$grandProfit = $grand['retail'] - $grand['cost'];

// Most valuable categories first
usort($rows, fn($a, $b) => $b['cost_value'] <=> $a['cost_value']);

// Rank in-stock variants by value, then take the top 10
$ranked = [];
foreach ($variants as $v) {
    if ($v['quantity'] > 0) {
        $v['value'] = $v['quantity'] * $v['unit_cost'];
        $ranked[]   = $v;
    }
}
usort($ranked, fn($a, $b) => $b['value'] <=> $a['value']);

$topParts = [];
while (count($topParts) < 10 && $ranked) {
    $topParts[] = array_shift($ranked);
}

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
                            <a href="item_view.php?id=<?= (int) $p['item_id'] ?>"><?= e($p['item_name']) ?></a>
                            <span class="muted">· <?= e($p['label']) ?></span>
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
