<?php
/**
 * stock.php — Stock levels, restocking & adjustments
 *
 * Every variant is listed in ONE table (picture, part, quantity, status, value).
 * Click "Update" on a row to open the Restock / Adjust forms for that variant.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle = 'Stock';

// Selected variant comes from the form (POST) or the link (GET)
$variantId = (int) ($_POST['variant_id'] ?? $_GET['variant'] ?? 0);

// ---------- Save a restock or an adjustment ----------
if (is_post()) {
    $action = $_POST['action'] ?? '';
    $qty    = (int) ($_POST['quantity'] ?? 0);
    $error  = '';

    if ($qty <= 0) {
        $error = 'Enter a quantity greater than zero.';
    } elseif ($action === 'restock') {
        $cost = (float) ($_POST['unit_cost'] ?? 0);
        if ($cost < 0) {
            $error = 'Unit cost cannot be negative.';
        } else {
            $error = change_stock($variantId, $qty, 'restock', $cost, trim($_POST['supplier'] ?? ''), trim($_POST['reference'] ?? ''));
            if ($error === '') {
                flash("Restocked $qty unit(s).");
            }
        }
    } elseif ($action === 'adjust') {
        // Removing stock makes the change negative
        $delta  = ($_POST['direction'] ?? '') === 'remove' ? -$qty : $qty;
        $reason = $_POST['reason'] ?? 'Other';
        $note   = trim($_POST['note'] ?? '');

        $error = change_stock($variantId, $delta, 'adjust', 0, '', $reason . ($note !== '' ? ' — ' . $note : ''));
        if ($error === '') {
            flash('Stock adjusted by ' . ($delta > 0 ? '+' : '') . $delta . '.');
        }
    }

    if ($error !== '') {
        flash($error, 'error');
    }

    // Go back to the same view (keep the table filters)
    $back = $_POST['back'] ?? '';
    redirect('stock.php?' . $back . ($back !== '' ? '&' : '') . 'variant=' . $variantId . '#stock-forms');
}

// ---------- Table filters ($_GET) ----------
$search     = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category'] ?? 0);
$level      = $_GET['level'] ?? '';

$where  = [];
$types  = '';
$params = [];

if ($search !== '') {
    $where[] = '(i.name LIKE ? OR i.brand LIKE ? OR v.label LIKE ? OR v.sku LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}
if ($categoryId > 0) {
    $where[] = 'i.category_id = ?';
    $types  .= 'i';
    $params[] = $categoryId;
}
if ($level === 'out') {
    $where[] = 'v.quantity <= 0';
} elseif ($level === 'low') {
    $where[] = 'v.quantity > 0 AND v.quantity <= v.low_threshold';
} elseif ($level === 'ok') {
    $where[] = 'v.quantity > v.low_threshold';
}

$rows = db_rows(
    'SELECT v.*, i.name AS item_name, i.brand, i.image, c.name AS category
     FROM variants v
     JOIN items i ON i.id = v.item_id
     LEFT JOIN categories c ON c.id = i.category_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY i.name, v.id',
    $types,
    $params
);

// Counts for the status tabs (whole inventory, ignores filters)
$counts = db_row(
    'SELECT COUNT(*) AS all_count,
            COALESCE(SUM(quantity > low_threshold), 0) AS ok_count,
            COALESCE(SUM(quantity > 0 AND quantity <= low_threshold), 0) AS low_count,
            COALESCE(SUM(quantity <= 0), 0) AS out_count
     FROM variants'
);

// Current filters as a query string, so links and forms can keep them
$filterQuery = http_build_query(['q' => $search, 'category' => $categoryId ?: '', 'level' => $level]);

// ---------- Selected variant (for the forms) ----------
$selected  = null;
$movements = [];
if ($variantId > 0) {
    $selected = db_row(
        'SELECT v.*, i.name AS item_name, i.image FROM variants v JOIN items i ON i.id = v.item_id WHERE v.id = ?',
        'i',
        [$variantId]
    );
    if ($selected) {
        $movements = movement_list('m.variant_id = ?', 'i', [$variantId], 8);
    }
}

$totalUnits = 0;
$totalValue = 0.0;
foreach ($rows as $r) {
    $totalUnits += $r['quantity'];
    $totalValue += $r['quantity'] * $r['unit_cost'];
}

$categories = all_categories();

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Stock</h1>
    <div class="tabs">
        <?php
        $tabs = ['' => ['All', $counts['all_count']], 'ok' => ['In stock', $counts['ok_count']], 'low' => ['Low', $counts['low_count']], 'out' => ['Out', $counts['out_count']]];
        foreach ($tabs as $key => $tab):
            $link = http_build_query(['q' => $search, 'category' => $categoryId ?: '', 'level' => $key]);
        ?>
            <a href="stock.php?<?= e($link) ?>" class="<?= $level === $key ? 'active' : '' ?>"><?= $tab[0] ?> <span class="muted">(<?= (int) $tab[1] ?>)</span></a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($selected): ?>
    <?php list($label, $class) = stock_status((int) $selected['quantity'], (int) $selected['low_threshold']); ?>
    <section class="card panel" id="stock-forms">
        <div class="panel-head">
            <div class="part-cell">
                <img class="thumb lg" src="<?= e(image_url('products', $selected['image'])) ?>" alt="">
                <div>
                    <strong><?= e($selected['item_name']) ?></strong> <span class="muted">· <?= e($selected['label']) ?></span>
                    <div><strong><?= (int) $selected['quantity'] ?> on hand</strong> <?= badge($label, $class) ?>
                        <span class="muted small">· low at <?= (int) $selected['low_threshold'] ?> · avg. cost <?= money($selected['unit_cost']) ?></span></div>
                </div>
            </div>
            <div class="actions">
                <a class="btn small" href="item_view.php?id=<?= (int) $selected['item_id'] ?>">View product</a>
                <a class="btn small ghost" href="stock.php?<?= e($filterQuery) ?>">Close ×</a>
            </div>
        </div>

        <div class="grid-2">
            <div>
                <h2>Restock</h2>
                <form method="post" class="form">
                    <input type="hidden" name="action" value="restock">
                    <input type="hidden" name="variant_id" value="<?= (int) $selected['id'] ?>">
                    <input type="hidden" name="back" value="<?= e($filterQuery) ?>">
                    <div class="row">
                        <label>Quantity received
                            <input type="number" name="quantity" min="1" required>
                        </label>
                        <label>Unit cost
                            <input type="number" name="unit_cost" min="0" step="0.01" value="<?= e($selected['unit_cost']) ?>">
                        </label>
                    </div>
                    <div class="row">
                        <label>Supplier
                            <input type="text" name="supplier" placeholder="e.g. PC Hub Wholesale">
                        </label>
                        <label>Reference / invoice #
                            <input type="text" name="reference" placeholder="Optional">
                        </label>
                    </div>
                    <div class="form-actions"><button class="btn primary">Add to stock</button></div>
                </form>
            </div>

            <div>
                <h2>Adjust</h2>
                <form method="post" class="form">
                    <input type="hidden" name="action" value="adjust">
                    <input type="hidden" name="variant_id" value="<?= (int) $selected['id'] ?>">
                    <input type="hidden" name="back" value="<?= e($filterQuery) ?>">
                    <div class="row">
                        <label>Direction
                            <select name="direction">
                                <option value="remove">Remove (−)</option>
                                <option value="add">Add (+)</option>
                            </select>
                        </label>
                        <label>Quantity
                            <input type="number" name="quantity" min="1" required>
                        </label>
                    </div>
                    <div class="row">
                        <label>Reason
                            <select name="reason">
                                <?php foreach (ADJUST_REASONS as $reason): ?>
                                    <option><?= e($reason) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Note
                            <input type="text" name="note" placeholder="Optional">
                        </label>
                    </div>
                    <div class="form-actions"><button class="btn">Save adjustment</button></div>
                </form>
            </div>
        </div>

        <h2>Recent movements</h2>
        <?php $showPart = false; ?>
        <?php include __DIR__ . '/includes/movements_table.php'; ?>
    </section>
<?php endif; ?>

<form method="get" class="filters">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search part, variant or SKU">
    <select name="category">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="hidden" name="level" value="<?= e($level) ?>">
    <button class="btn">Filter</button>
    <?php if ($search !== '' || $categoryId > 0 || $level !== ''): ?><a class="btn ghost" href="stock.php">Clear</a><?php endif; ?>
</form>

<section class="card">
    <?php if (!$rows): ?>
        <p class="empty">No stock rows match these filters.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="stock-table">
                <thead>
                <tr>
                    <th>Part</th>
                    <th>Variant</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th class="num">On hand</th>
                    <th class="num">Min</th>
                    <th>Status</th>
                    <th class="num">Avg. cost</th>
                    <th class="num">Value</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php list($label, $class) = stock_status((int) $r['quantity'], (int) $r['low_threshold']); ?>
                    <tr class="<?= $selected && (int) $selected['id'] === (int) $r['id'] ? 'editing' : '' ?>">
                        <td>
                            <div class="part-cell">
                                <img class="thumb" src="<?= e(image_url('products', $r['image'])) ?>" alt="">
                                <a href="item_view.php?id=<?= (int) $r['item_id'] ?>"><?= e($r['item_name']) ?></a>
                            </div>
                        </td>
                        <td><?= e($r['label']) ?></td>
                        <td class="muted small"><?= e($r['sku']) ?></td>
                        <td class="muted"><?= e($r['category'] ?? '—') ?></td>
                        <td class="num"><strong><?= (int) $r['quantity'] ?></strong></td>
                        <td class="num muted"><?= (int) $r['low_threshold'] ?></td>
                        <td><?= badge($label, $class) ?></td>
                        <td class="num"><?= money($r['unit_cost']) ?></td>
                        <td class="num"><?= money($r['quantity'] * $r['unit_cost']) ?></td>
                        <td class="num"><a class="btn small" href="stock.php?<?= e($filterQuery) ?>&amp;variant=<?= (int) $r['id'] ?>#stock-forms">Update</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr>
                    <th colspan="4"><?= count($rows) ?> variant(s)</th>
                    <th class="num"><?= number_format($totalUnits) ?></th>
                    <th colspan="3"></th>
                    <th class="num"><?= money($totalValue) ?></th>
                    <th></th>
                </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
