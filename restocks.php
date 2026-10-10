<?php
/**
 * restocks.php - Restock history
 * Every restock with supplier and cost, filterable by date and supplier.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Restock history';

$from     = $_GET['from'] ?? '';
$to       = $_GET['to'] ?? '';
$supplier = trim($_GET['supplier'] ?? '');

$restocks   = [];
$totalUnits = 0;
$totalSpent = 0.0;

// Only movements of type 'restock'; filters and totals are applied in the same pass
foreach (movement_list(null, 'restock') as $r) {
    // Date-only part of the timestamp, so string comparison with the date filters works (YYYY-MM-DD)
    $day = substr($r['created_at'], 0, 10);

    if (($from !== '' && $day < $from) || ($to !== '' && $day > $to)) {
        continue;
    }
    // Supplier filter is a case-insensitive partial match
    if ($supplier !== '' && stripos($r['supplier'], $supplier) === false) {
        continue;
    }

    // Totals only include rows that passed the filters
    $restocks[]  = $r;
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
                <tr><th>Date</th><th>Part</th><th class="num">Qty</th><th class="num">Unit cost</th><th class="num">Total</th><th>Supplier</th></tr>
                </thead>
                <tbody>
                <?php foreach ($restocks as $r): ?>
                    <tr>
                        <td class="nowrap muted"><?= e(nice_date($r['created_at'])) ?></td>
                        <td>
                            <a href="item_view.php?id=<?= $r['item_id'] ?>"><?= e($r['item_name']) ?></a>
                            <span class="muted">· <?= e($r['label']) ?></span>
                        </td>
                        <td class="num">+<?= $r['qty_change'] ?></td>
                        <td class="num"><?= money($r['unit_cost']) ?></td>
                        <td class="num"><?= money($r['qty_change'] * $r['unit_cost']) ?></td>
                        <td><?= e($r['supplier'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>