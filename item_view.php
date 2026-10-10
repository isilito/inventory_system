<?php
/**
 * item_view.php — Item detail
 * Shows an item's variants (variant tracking), lets you add/edit/delete
 * variants, and lists the item's stock history.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$id   = (int) ($_GET['id'] ?? 0);
$item = find_row('items', $id);

if ($item === null) {
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
                $fields = [
                    'label'         => trim($_POST['label'] ?? ''),
                    'sku'           => trim($_POST['sku'] ?? ''),
                    'unit_cost'     => (float) ($_POST['unit_cost'] ?? 0),
                    'sell_price'    => (float) ($_POST['sell_price'] ?? 0),
                    'low_threshold' => max(0, (int) ($_POST['low_threshold'] ?? 0)),
                ];

                if ($fields['label'] === '') {
                    throw new RuntimeException('Variant label is required.');
                }
                if ($fields['unit_cost'] < 0 || $fields['sell_price'] < 0) {
                    throw new RuntimeException('Cost and price cannot be negative.');
                }

                $existing = find_row('variants', $variantId);
                if ($existing !== null && $existing['item_id'] === $id) {
                    // Quantity is never edited here — use Stock so the change is logged
                    update_row('variants', $variantId, $fields);
                    flash('Variant updated.');
                } else {
                    $newId = insert_row('variants', ['item_id' => $id, 'quantity' => 0] + $fields);
                    $qty   = max(0, (int) ($_POST['quantity'] ?? 0));
                    if ($qty > 0) {
                        change_stock($newId, $qty, 'initial', ['reason' => 'Opening stock count']);
                    }
                    flash('Variant added.');
                }
                save_data();
                break;

            case 'delete_variant':
                $variant = find_row('variants', (int) ($_POST['variant_id'] ?? 0));
                if ($variant !== null && $variant['item_id'] === $id) {
                    delete_variant($variant['id']);
                    save_data();
                    flash('Variant deleted.');
                }
                break;

            case 'delete_item':
                delete_item($id);
                save_data();
                flash('Item "' . $item['name'] . '" deleted.');
                redirect('items.php');
        }
    } catch (RuntimeException $ex) {
        // Nothing was saved, so the data file is unchanged
        flash($ex->getMessage(), 'error');
    }

    redirect("item_view.php?id=$id");
}

$variants = item_variants($id);

// Variant currently being edited
$editVariant = null;
$editId = (int) ($_GET['edit_variant'] ?? 0);
foreach ($variants as $v) {
    if ($v['id'] === $editId) {
        $editVariant = $v;
        break;
    }
}

// Totals across variants
$totalQty   = 0;
$totalValue = 0.0;
foreach ($variants as $v) {
    $totalQty   += $v['quantity'];
    $totalValue += $v['quantity'] * $v['unit_cost'];
}

$movements = array_slice(movement_list(array_column($variants, 'id')), 0, 20);

$pageTitle = $item['name'];
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <a class="small muted" href="items.php">← Items</a>
        <h1><?= e($item['name']) ?> <?= badge($item['status'], tag_class($item['status'])) ?></h1>
        <p class="muted">
            <?= e($item['brand'] ?: 'No brand') ?> · <?= e(category_name($item['category_id'])) ?>
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
                    <?php [$label, $class] = stock_status($v['quantity'], $v['low_threshold']); ?>
                    <tr>
                        <td><strong><?= e($v['label']) ?></strong></td>
                        <td class="muted"><?= e($v['sku']) ?></td>
                        <td class="num"><?= $v['quantity'] ?></td>
                        <td class="num muted"><?= $v['low_threshold'] ?></td>
                        <td><?= badge($label, $class) ?></td>
                        <td class="num"><?= money($v['unit_cost']) ?></td>
                        <td class="num"><?= money($v['sell_price']) ?></td>
                        <td class="num nowrap">
                            <a class="btn small" href="stock.php?variant=<?= $v['id'] ?>">Stock</a>
                            <a class="btn small" href="item_view.php?id=<?= $id ?>&edit_variant=<?= $v['id'] ?>#variant-form">Edit</a>
                            <form method="post" class="inline" onsubmit="return confirm('Delete this variant and its stock history?');">
                                <input type="hidden" name="action" value="delete_variant">
                                <input type="hidden" name="variant_id" value="<?= $v['id'] ?>">
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
        <input type="hidden" name="variant_id" value="<?= $editVariant['id'] ?? 0 ?>">
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
                <span class="muted small">To change quantity, use <a href="stock.php?variant=<?= $editVariant['id'] ?>">Stock</a>.</span>
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
