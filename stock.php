<?php
/**
 * stock.php — Stock adjustment & restocking
 * Pick a variant, then either record a restock from a supplier
 * or make a manual adjustment (damaged, count correction, etc).
 */

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$pageTitle = 'Stock';

// Selected variant comes from the form (POST) or the link (GET)
$variantId = (int) ($_POST['variant_id'] ?? $_GET['variant'] ?? 0);

if (is_post()) {
    $action = $_POST['action'] ?? '';
    $qty    = (int) ($_POST['quantity'] ?? 0);

    try {
        if ($qty <= 0) {
            throw new RuntimeException('Enter a quantity greater than zero.');
        }

        $pdo->beginTransaction();

        if ($action === 'restock') {
            $cost = (float) ($_POST['unit_cost'] ?? 0);
            if ($cost < 0) {
                throw new RuntimeException('Unit cost cannot be negative.');
            }
            change_stock($variantId, $qty, 'restock', [
                'unit_cost' => $cost,
                'supplier'  => trim($_POST['supplier'] ?? ''),
                'reason'    => trim($_POST['reference'] ?? ''),
            ]);
            flash("Restocked $qty unit(s).");
        } elseif ($action === 'adjust') {
            // Removing stock makes the change negative
            $delta  = ($_POST['direction'] ?? '') === 'remove' ? -$qty : $qty;
            $reason = $_POST['reason'] ?? 'Other';
            $note   = trim($_POST['note'] ?? '');

            change_stock($variantId, $delta, 'adjust', [
                'reason' => $reason . ($note !== '' ? ' — ' . $note : ''),
            ]);
            flash('Stock adjusted by ' . ($delta > 0 ? '+' : '') . $delta . '.');
        }

        $pdo->commit();
    } catch (RuntimeException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash($ex->getMessage(), 'error');
    }

    redirect("stock.php?variant=$variantId");
}

// All variants for the picker
$allVariants = $pdo->query(
    'SELECT v.id, v.label, v.quantity, i.name AS item_name
     FROM variants v JOIN items i ON i.id = v.item_id
     ORDER BY i.name, v.label'
)->fetchAll();

// Details + history for the selected variant
$selected  = null;
$movements = [];
if ($variantId > 0) {
    $stmt = $pdo->prepare(
        'SELECT v.*, i.name AS item_name, i.status
         FROM variants v JOIN items i ON i.id = v.item_id WHERE v.id = ?'
    );
    $stmt->execute([$variantId]);
    $selected = $stmt->fetch() ?: null;

    if ($selected) {
        $stmt = $pdo->prepare(
            'SELECT m.*, v.label, v.item_id, i.name AS item_name
             FROM stock_movements m
             JOIN variants v ON v.id = m.variant_id
             JOIN items i ON i.id = v.item_id
             WHERE m.variant_id = ?
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT 10'
        );
        $stmt->execute([$variantId]);
        $movements = $stmt->fetchAll();
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Stock</h1>
</div>

<form method="get" class="filters">
    <select name="variant" onchange="this.form.submit()" class="grow">
        <option value="">Choose a part…</option>
        <?php foreach ($allVariants as $v): ?>
            <option value="<?= (int) $v['id'] ?>" <?= $variantId === (int) $v['id'] ? 'selected' : '' ?>>
                <?= e($v['item_name'] . ' — ' . $v['label'] . ' (' . $v['quantity'] . ' on hand)') ?>
            </option>
        <?php endforeach; ?>
    </select>
    <noscript><button class="btn">Select</button></noscript>
</form>

<?php if (!$selected): ?>
    <section class="card"><p class="empty">Select a part above to restock or adjust it.</p></section>
<?php else: ?>
    <?php [$label, $class] = stock_status((int) $selected['quantity'], (int) $selected['low_threshold']); ?>

    <p class="summary">
        <strong><?= (int) $selected['quantity'] ?> on hand</strong> <?= badge($label, $class) ?>
        <span class="muted">· low at <?= (int) $selected['low_threshold'] ?> · avg. cost <?= money($selected['unit_cost']) ?> ·</span>
        <a href="item_view.php?id=<?= (int) $selected['item_id'] ?>">View item</a>
    </p>

    <div class="grid-2">
        <section class="card">
            <h2>Restock</h2>
            <form method="post" class="form">
                <input type="hidden" name="action" value="restock">
                <input type="hidden" name="variant_id" value="<?= (int) $selected['id'] ?>">
                <div class="row">
                    <label>Quantity received
                        <input type="number" name="quantity" min="1" required>
                    </label>
                    <label>Unit cost
                        <input type="number" name="unit_cost" min="0" step="0.01" value="<?= e($selected['unit_cost']) ?>">
                    </label>
                </div>
                <label>Supplier
                    <input type="text" name="supplier" placeholder="e.g. PC Hub Wholesale">
                </label>
                <label>Reference / invoice #
                    <input type="text" name="reference" placeholder="Optional">
                </label>
                <div class="form-actions"><button class="btn primary">Add to stock</button></div>
            </form>
        </section>

        <section class="card">
            <h2>Adjust</h2>
            <form method="post" class="form">
                <input type="hidden" name="action" value="adjust">
                <input type="hidden" name="variant_id" value="<?= (int) $selected['id'] ?>">
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
                <div class="form-actions"><button class="btn">Save adjustment</button></div>
            </form>
        </section>
    </div>

    <section class="card">
        <h2>Recent movements</h2>
        <?php $showPart = false; ?>
        <?php include __DIR__ . '/includes/movements_table.php'; ?>
    </section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
