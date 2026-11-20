<?php
/**
 * item_view.php — Product detail
 * Shows the product's picture and variants, lets you add several variants
 * at once, edit/delete a variant, and lists the product's stock history.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$id   = (int) ($_GET['id'] ?? 0);
$item = db_row(
    'SELECT i.*, c.name AS category FROM items i LEFT JOIN categories c ON c.id = i.category_id WHERE i.id = ?',
    'i',
    [$id]
);

if ($item === null) {
    flash('Product not found.', 'error');
    redirect('items.php');
}

$variantRows = [];
$addErrors   = [];

// ---------- Handle the forms on this page ----------
if (is_post()) {
    // Hidden input <input name="action"> tells us which form was submitted
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_variants':
            list($variantRows, $addErrors) = variants_from_post();

            // Labels must be unique within the product (new rows + existing ones)
            $labels = array_map('strtolower', array_column($variantRows, 'label'));
            foreach (item_variants($id) as $v) {
                $labels[] = strtolower($v['label']);
            }
            if (count($labels) !== count(array_unique($labels))) {
                $addErrors[] = 'Each variant needs a different label.';
            }
            if (count($variantRows) === 0) {
                $addErrors[] = 'Fill in at least one variant row.';
            }

            if (!$addErrors) {
                insert_variants($id, $variantRows);
                flash(count($variantRows) . ' variant(s) added.');
                redirect("item_view.php?id=$id");
            }
            break;   // fall through to the page so the errors and typed rows are shown

        case 'save_variant':
            $variantId = (int) ($_POST['variant_id'] ?? 0);
            $label     = trim($_POST['label'] ?? '');
            $sku       = trim($_POST['sku'] ?? '');
            $cost      = (float) ($_POST['unit_cost'] ?? 0);
            $price     = (float) ($_POST['sell_price'] ?? 0);
            $threshold = max(0, (int) ($_POST['low_threshold'] ?? 0));

            if ($label === '') {
                flash('Variant label is required.', 'error');
            } elseif ($cost < 0 || $price < 0) {
                flash('Cost and price cannot be negative.', 'error');
            } else {
                // "AND item_id = ?" makes sure the variant belongs to this product
                // Quantity is never edited here — use Stock so the change is logged
                db_run(
                    'UPDATE variants SET label = ?, sku = ?, unit_cost = ?, sell_price = ?, low_threshold = ? WHERE id = ? AND item_id = ?',
                    'ssddiii',
                    [$label, $sku, $cost, $price, $threshold, $variantId, $id]
                );
                flash('Variant updated.');
            }
            redirect("item_view.php?id=$id");

        case 'delete_variant':
            // Its stock history is removed too (ON DELETE CASCADE)
            db_run('DELETE FROM variants WHERE id = ? AND item_id = ?', 'ii', [(int) ($_POST['variant_id'] ?? 0), $id]);
            flash('Variant deleted.');
            redirect("item_view.php?id=$id");

        case 'delete_item':
            delete_image('products', $item['image']);    // remove the picture file from disk
            db_run('DELETE FROM items WHERE id = ?', 'i', [$id]);
            flash('Product "' . $item['name'] . '" deleted.');
            redirect('items.php');
    }
}

$variants = item_variants($id);

// Variant currently being edited (from the link ?edit_variant=ID)
$editVariant = null;
$editId = (int) ($_GET['edit_variant'] ?? 0);
foreach ($variants as $v) {
    if ((int) $v['id'] === $editId) {
        $editVariant = $v;
    }
}

// Totals across variants
$totalQty   = 0;
$totalValue = 0.0;
foreach ($variants as $v) {
    $totalQty   += $v['quantity'];
    $totalValue += $v['quantity'] * $v['unit_cost'];
}

$movements = movement_list('v.item_id = ?', 'i', [$id], 20);

$pageTitle = $item['name'];
include __DIR__ . '/includes/header.php';
?>

<a class="back-link" href="items.php">← Products</a>

<div class="product-head">
    <div class="product-photo">
        <img src="<?= e(image_url('products', $item['image'])) ?>" alt="<?= e($item['name']) ?>">
    </div>
    <div class="product-info">
        <h1><?= e($item['name']) ?> <?= badge($item['status'], tag_class($item['status'])) ?></h1>
        <p class="muted"><?= e($item['brand'] ?: 'No brand') ?> · <?= e($item['category'] ?? 'Uncategorized') ?></p>
        <div class="stats compact">
            <div class="stat"><span class="stat-label">Variants</span><span class="stat-value"><?= count($variants) ?></span></div>
            <div class="stat"><span class="stat-label">Units</span><span class="stat-value"><?= $totalQty ?></span></div>
            <div class="stat"><span class="stat-label">Value at cost</span><span class="stat-value"><?= money($totalValue) ?></span></div>
        </div>
        <?php if (!empty($item['notes'])): ?><p><?= nl2br(e($item['notes'])) ?></p><?php endif; ?>
        <div class="actions">
            <a class="btn" href="item_form.php?id=<?= $id ?>">Edit product</a>
            <form method="post" class="inline" onsubmit="return confirm('Delete this product, its picture, all its variants and their history?');">
                <input type="hidden" name="action" value="delete_item">
                <button class="btn danger">Delete</button>
            </form>
        </div>
    </div>
</div>

<section class="card">
    <h2>Variants</h2>
    <?php if (!$variants): ?>
        <p class="empty">No variants yet — add some below.</p>
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
                    <?php list($label, $class) = stock_status($v['quantity'], $v['low_threshold']); ?>
                    <tr class="<?= $editVariant && $editVariant['id'] == $v['id'] ? 'editing' : '' ?>">
                        <td><strong><?= e($v['label']) ?></strong></td>
                        <td class="muted"><?= e($v['sku']) ?></td>
                        <td class="num"><?= (int) $v['quantity'] ?></td>
                        <td class="num muted"><?= (int) $v['low_threshold'] ?></td>
                        <td><?= badge($label, $class) ?></td>
                        <td class="num"><?= money($v['unit_cost']) ?></td>
                        <td class="num"><?= money($v['sell_price']) ?></td>
                        <td class="num nowrap">
                            <a class="btn small" href="stock.php?variant=<?= (int) $v['id'] ?>#stock-forms">Stock</a>
                            <a class="btn small" href="item_view.php?id=<?= $id ?>&edit_variant=<?= (int) $v['id'] ?>#edit-variant">Edit</a>
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

<?php if ($editVariant): ?>
<section class="card" id="edit-variant">
    <h2>Edit variant — <?= e($editVariant['label']) ?></h2>
    <form method="post" class="form">
        <input type="hidden" name="action" value="save_variant">
        <input type="hidden" name="variant_id" value="<?= (int) $editVariant['id'] ?>">
        <div class="row">
            <label>Label
                <input type="text" name="label" required value="<?= e($editVariant['label']) ?>">
            </label>
            <label>SKU
                <input type="text" name="sku" value="<?= e($editVariant['sku']) ?>">
            </label>
            <label>Unit cost
                <input type="number" name="unit_cost" min="0" step="0.01" value="<?= e($editVariant['unit_cost']) ?>">
            </label>
            <label>Sell price
                <input type="number" name="sell_price" min="0" step="0.01" value="<?= e($editVariant['sell_price']) ?>">
            </label>
            <label>Low-stock at
                <input type="number" name="low_threshold" min="0" value="<?= e($editVariant['low_threshold']) ?>">
            </label>
        </div>
        <div class="form-actions">
            <button class="btn primary">Save variant</button>
            <a class="btn" href="item_view.php?id=<?= $id ?>">Cancel</a>
            <span class="muted small">To change quantity, use <a href="stock.php?variant=<?= (int) $editVariant['id'] ?>#stock-forms">Stock</a>.</span>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="card" id="add-variants">
    <h2>Add variants</h2>
    <?php if ($addErrors): ?>
        <div class="flash flash-error"><?php foreach ($addErrors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
    <?php endif; ?>
    <form method="post" class="form">
        <input type="hidden" name="action" value="add_variants">
        <?php include __DIR__ . '/includes/variant_rows.php'; ?>
        <div class="form-actions"><button class="btn primary">Save variants</button></div>
    </form>
</section>

<section class="card">
    <h2>Stock history</h2>
    <?php $showPart = false; ?>
    <?php include __DIR__ . '/includes/movements_table.php'; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
