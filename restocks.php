<?php
/**
 * restocks.php — Restock history
 * Every restock with supplier and cost, filterable by date and supplier.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle = 'Restock history';

$from     = $_GET['from'] ?? '';
$to       = $_GET['to'] ?? '';
$supplier = trim($_GET['supplier'] ?? '');

// Build the filter conditions
$where  = "m.type = 'restock'";
$types  = '';
$params = [];

if ($from !== '') {
    $where   .= ' AND DATE(m.created_at) >= ?';
    $types   .= 's';
    $params[] = $from;
}
if ($to !== '') {
    $where   .= ' AND DATE(m.created_at) <= ?';
    $types   .= 's';
    $params[] = $to;
}
if ($supplier !== '') {
    $where   .= ' AND m.supplier LIKE ?';
    $types   .= 's';
    $params[] = '%' . $supplier . '%';
}

$restocks = movement_list($where, $types, $params, 500);

$totalUnits = 0;
$totalSpent = 0.0;
foreach ($restocks as $r) {
    $totalUnits += $r['qty_change'];
    $totalSpent += $r['qty_change'] * $r['unit_cost'];
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Restock history</h1>
</div>

<form method="get" class="filters">
    <label class="inline-label">From <input type="date" name="from" value="<?= e($from) ?>"></label>
    <label class="inline-label">To <input type="date" name="to" value="<?= e($to) ?>"></label>
    <input type="search" name="supplier" value="<?= e($supplier) ?>" placeholder="Supplier">
    <button class="btn">Filter</button>
    <?php if ($from || $to || $supplier): ?><a class="btn ghost" href="restocks.php">Clear</a><?php endif; ?>
</form>

<section class="card">
    <?php if (!$restocks): ?>
        <p class="empty">No restocks found.</p>
    <?php else: ?>
        <p class="summary muted"><?= count($restocks) ?> restocks · <?= $totalUnits ?> units · <?= money($totalSpent) ?> spent</p>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Date</th><th>Part</th><th class="num">Qty</th><th class="num">Unit cost</th><th class="num">Total</th><th>Supplier</th><th>Ref.</th><th>By</th></tr>
                </thead>
                <tbody>
                <?php foreach ($restocks as $r): ?>
                    <tr>
                        <td class="nowrap muted"><?= e(nice_date($r['created_at'])) ?></td>
                        <td class="cell-main">
                            <a href="item_view.php?id=<?= (int) $r['item_id'] ?>"><?= e($r['item_name']) ?></a>
                            <span class="muted">· <?= e($r['label']) ?></span>
                        </td>
                        <td class="num">+<?= (int) $r['qty_change'] ?></td>
                        <td class="num"><?= money($r['unit_cost']) ?></td>
                        <td class="num"><?= money($r['qty_change'] * $r['unit_cost']) ?></td>
                        <td><?= e($r['supplier'] ?: '—') ?></td>
                        <td class="muted small"><?= e($r['reason']) ?></td>
                        <td class="muted small"><?= e($r['username'] ?? 'system') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
