<?php
/**
 * item_view.php — Item detail
 * Shows an item's variants (variant tracking), lets you add/edit/delete
 * variants, and lists the item's stock history.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$id  = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT i.*, c.name AS category FROM items i LEFT JOIN categories c ON c.id = i.category_id WHERE i.id = ?'
);
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    flash('Item not found.', 'error');
    redirect('items.php');
}

// Handle variant and item actions
if (is_post()) {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'save_variant':
                $variantId = (int) ($_POST['variant_id'] ?? 0);
                $label     = trim($_POST['label'] ?? '');
                $sku       = trim($_POST['sku'] ?? '');
                $cost      = (float) ($_POST['unit_cost'] ?? 0);
                $price     = (float) ($_POST['sell_price'] ?? 0);
                $threshold = max(0, (int) ($_POST['low_threshold'] ?? 0));

                if ($label === '') {
                    throw new RuntimeException('Variant label is required.');
                }
                if ($cost < 0 || $price < 0) {
                    throw new RuntimeException('Cost and price cannot be negative.');
                }

                if ($variantId > 0) {
                    // Quantity is never edited here — use Stock adjustments so it is logged
                    $pdo->prepare(
                        'UPDATE variants SET label = ?, sku = ?, unit_cost = ?, sell_price = ?, low_threshold = ?
                         WHERE id = ? AND item_id = ?'
                    )->execute([$label, $sku, $cost, $price, $threshold, $variantId, $id]);
                    flash('Variant updated.');
                } else {
                    $qty = max(0, (int) ($_POST['quantity'] ?? 0));

                    $pdo->beginTransaction();
                    $pdo->prepare(
                        'INSERT INTO variants (item_id, label, sku, quantity, unit_cost, sell_price, low_threshold)
                         VALUES (?, ?, ?, 0, ?, ?, ?)'
                    )->execute([$id, $label, $sku, $cost, $price, $threshold]);
                    if ($qty > 0) {
                        change_stock((int) $pdo->lastInsertId(), $qty, 'initial', ['reason' => 'Opening stock count']);
                    }
                    $pdo->commit();
                    flash('Variant added.');
                }
                break;

            case 'delete_variant':
                $pdo->prepare('DELETE FROM variants WHERE id = ? AND item_id = ?')
                    ->execute([(int) $_POST['variant_id'], $id]);
                flash('Variant deleted.');
                break;

            case 'delete_item':
                $pdo->prepare('DELETE FROM items WHERE id = ?')->execute([$id]);
                flash('Item "' . $item['name'] . '" deleted.');
                redirect('items.php');
        }
    } catch (RuntimeException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash($ex->getMessage(), 'error');
    }

    redirect("item_view.php?id=$id");
}

$stmt = $pdo->prepare('SELECT * FROM variants WHERE item_id = ? ORDER BY id');
$stmt->execute([$id]);
$variants = $stmt->fetchAll();

// Variant currently being edited
$editVariant = null;
$editId = (int) ($_GET['edit_variant'] ?? 0);
foreach ($variants as $v) {
    if ((int) $v['id'] === $editId) {
        $editVariant = $v;
        break;
    }
}

// Totals across variants
$totalQty = 0;
$totalValue = 0.0;
foreach ($variants as $v) {
    $totalQty   += $v['quantity'];
    $totalValue += $v['quantity'] * $v['unit_cost'];
}

$stmt = $pdo->prepare(
    'SELECT m.*, v.label, v.item_id, ? AS item_name
     FROM stock_movements m
     JOIN variants v ON v.id = m.variant_id
     WHERE v.item_id = ?
     ORDER BY m.created_at DESC, m.id DESC
     LIMIT 20'
);
$stmt->execute([$item['name'], $id]);
$movements = $stmt->fetchAll();

$pageTitle = $item['name'];
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <a class="small muted" href="items.php">← Items</a>
        <h1><?= e($item['name']) ?> <?= badge($item['status'], tag_class($item['status'])) ?></h1>
        <p class="muted">
            <?= e($item['brand'] ?: 'No brand') ?> · <?= e($item['category'] ?? 'Uncategorized') ?>
            · <?= $totalQty ?> units · <?= money($totalValue) ?> at cost
        </p>
        <?php if ($item['notes'] !== ''): ?><p><?= nl2br(e($item['notes'])) ?></p><?php endif; ?>
    </div>
    <div class="actions">
        <a class="btn" href="item_form.php?id=<?= $id ?>">Edit item</a>
        <form method="post" class="inline" onsubmit="return confirm('Delete this item, all its variants and their history?');">
            <input type="hidden" name="action" value="delete_item">
            <button class="btn danger">Delete</button>
        </form>
    </div>
</div>

<section class="card">
    <h2>Variants</h2>
    <?php if (!$variants): ?>
        <p class="empty">No variants yet — add one below.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Variant</th><th>SKU</th><th class="num">Qty</th><th class="num">Min</th><th>Stock</th>
                    <th class="num">Cost</th><th class="num">Price</th><th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($variants as $v): ?>
                    <?php [$label, $class] = stock_status((int) $v['quantity'], (int) $v['low_threshold']); ?>
                    <tr>
                        <td><strong><?= e($v['label']) ?></strong></td>
                        <td class="muted"><?= e($v['sku']) ?></td>
                        <td class="num"><?= (int) $v['quantity'] ?></td>
                        <td class="num muted"><?= (int) $v['low_threshold'] ?></td>
                        <td><?= badge($label, $class) ?></td>
                        <td class="num"><?= money($v['unit_cost']) ?></td>
                        <td class="num"><?= money($v['sell_price']) ?></td>
                        <td class="num nowrap">
                            <a class="btn small" href="stock.php?variant=<?= (int) $v['id'] ?>">Stock</a>
                            <a class="btn small" href="item_view.php?id=<?= $id ?>&edit_variant=<?= (int) $v['id'] ?>#variant-form">Edit</a>
                            <form method="post" class="inline" onsubmit="return confirm('Delete this variant and its stock history?');">
                                <input type="hidden" name="action" value="delete_variant">
                                <input type="hidden" name="variant_id" value="<?= (int) $v['id'] ?>">
                                <button class="btn small danger">×</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card" id="variant-form">
    <h2><?= $editVariant ? 'Edit variant' : 'Add variant' ?></h2>
    <form method="post" class="form">
        <input type="hidden" name="action" value="save_variant">
        <input type="hidden" name="variant_id" value="<?= (int) ($editVariant['id'] ?? 0) ?>">
        <div class="row">
            <label>Label
                <input type="text" name="label" required value="<?= e($editVariant['label'] ?? '') ?>" placeholder="e.g. 16GB / White">
            </label>
            <label>SKU
                <input type="text" name="sku" value="<?= e($editVariant['sku'] ?? '') ?>">
            </label>
            <?php if (!$editVariant): ?>
                <label>Opening qty
                    <input type="number" name="quantity" min="0" value="0">
                </label>
            <?php endif; ?>
            <label>Unit cost
                <input type="number" name="unit_cost" min="0" step="0.01" value="<?= e($editVariant['unit_cost'] ?? '') ?>">
            </label>
            <label>Sell price
                <input type="number" name="sell_price" min="0" step="0.01" value="<?= e($editVariant['sell_price'] ?? '') ?>">
            </label>
            <label>Low-stock at
                <input type="number" name="low_threshold" min="0" value="<?= e($editVariant['low_threshold'] ?? DEFAULT_THRESHOLD) ?>">
            </label>
        </div>
        <div class="form-actions">
            <button class="btn primary"><?= $editVariant ? 'Save variant' : 'Add variant' ?></button>
            <?php if ($editVariant): ?>
                <a class="btn" href="item_view.php?id=<?= $id ?>">Cancel</a>
                <span class="muted small">To change quantity, use <a href="stock.php?variant=<?= (int) $editVariant['id'] ?>">Stock</a>.</span>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="card">
    <h2>Stock history</h2>
    <?php $showPart = false; ?>
    <?php include __DIR__ . '/includes/movements_table.php'; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
