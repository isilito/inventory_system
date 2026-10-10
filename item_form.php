<?php
/**
 * item_form.php — Item registration (create & edit)
 * New items are created together with their first variant.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$id     = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
$errors = [];

// Load the item when editing
$item = $isEdit ? find_row('items', $id) : null;
if ($isEdit && $item === null) {
    flash('Item not found.', 'error');
    redirect('items.php');
}

// Form values: posted data > existing item > defaults
$form = [
    'name'          => $item['name'] ?? '',
    'brand'         => $item['brand'] ?? '',
    'category_id'   => $item['category_id'] ?? '',
    'status'        => $item['status'] ?? 'New',
    'notes'         => $item['notes'] ?? '',
    // first-variant fields (create only)
    'label'         => 'Standard',
    'sku'           => '',
    'quantity'      => 0,
    'unit_cost'     => '',
    'sell_price'    => '',
    'low_threshold' => DEFAULT_THRESHOLD,
];

if (is_post()) {
    // Overwrite defaults with whatever was submitted
    foreach ($form as $key => $default) {
        $form[$key] = trim($_POST[$key] ?? (string) $default);
    }

    // Validation
    if ($form['name'] === '') {
        $errors[] = 'Item name is required.';
    }
    if (!in_array($form['status'], STATUS_TAGS, true)) {
        $errors[] = 'Choose a valid status tag.';
    }
    if (!$isEdit) {
        if ($form['label'] === '') {
            $errors[] = 'Variant label is required.';
        }
        if ((int) $form['quantity'] < 0 || (float) $form['unit_cost'] < 0 || (float) $form['sell_price'] < 0) {
            $errors[] = 'Quantity, cost and price cannot be negative.';
        }
    }

    if (!$errors) {
        $fields = [
            'category_id' => $form['category_id'] !== '' ? (int) $form['category_id'] : null,
            'name'        => $form['name'],
            'brand'       => $form['brand'],
            'status'      => $form['status'],
            'notes'       => $form['notes'],
        ];

        if ($isEdit) {
            update_row('items', $id, $fields);
            save_data();
            flash('Item updated.');
            redirect("item_view.php?id=$id");
        }

        // Create the item and its first variant
        $newId = insert_row('items', $fields + ['created_at' => date('Y-m-d H:i:s')]);

        $variantId = insert_row('variants', [
            'item_id'       => $newId,
            'label'         => $form['label'],
            'sku'           => $form['sku'],
            'quantity'      => 0,
            'unit_cost'     => (float) $form['unit_cost'],
            'sell_price'    => (float) $form['sell_price'],
            'low_threshold' => max(0, (int) $form['low_threshold']),
        ]);

        // Log the opening quantity so it shows in the stock history
        if ((int) $form['quantity'] > 0) {
            change_stock($variantId, (int) $form['quantity'], 'initial', ['reason' => 'Opening stock count']);
        }

        save_data();
        flash('Item registered.');
        redirect("item_view.php?id=$newId");
    }
}

$categories = rows('categories');
usort($categories, fn($a, $b) => strcasecmp($a['name'], $b['name']));

$pageTitle = $isEdit ? 'Edit item' : 'New item';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1><?= e($pageTitle) ?></h1>
    <a class="btn ghost" href="<?= $isEdit ? "item_view.php?id=$id" : 'items.php' ?>">← Back</a>
</div>

<?php if ($errors): ?>
    <div class="flash flash-error">
        <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" class="card form narrow">
    <h2>Item details</h2>
    <label>Name
        <input type="text" name="name" required value="<?= e($form['name']) ?>" placeholder="e.g. GeForce RTX 4060">
    </label>
    <div class="row">
        <label>Brand
            <input type="text" name="brand" value="<?= e($form['brand']) ?>" placeholder="e.g. MSI">
        </label>
        <label>Category
            <select name="category_id">
                <option value="">— None —</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (string) $form['category_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Status tag
            <select name="status">
                <?php foreach (STATUS_TAGS as $t): ?>
                    <option <?= $form['status'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <label>Notes
        <textarea name="notes" rows="2" placeholder="Optional"><?= e($form['notes']) ?></textarea>
    </label>

    <?php if (!$isEdit): ?>
        <h2>First variant</h2>
        <p class="muted small">You can add more variants (capacity, color, model…) after saving.</p>
        <div class="row">
            <label>Variant label
                <input type="text" name="label" required value="<?= e($form['label']) ?>" placeholder="e.g. 8GB / Black">
            </label>
            <label>SKU
                <input type="text" name="sku" value="<?= e($form['sku']) ?>">
            </label>
        </div>
        <div class="row">
            <label>Opening qty
                <input type="number" name="quantity" min="0" value="<?= e($form['quantity']) ?>">
            </label>
            <label>Unit cost
                <input type="number" name="unit_cost" min="0" step="0.01" value="<?= e($form['unit_cost']) ?>">
            </label>
            <label>Sell price
                <input type="number" name="sell_price" min="0" step="0.01" value="<?= e($form['sell_price']) ?>">
            </label>
            <label>Low-stock at
                <input type="number" name="low_threshold" min="0" value="<?= e($form['low_threshold']) ?>">
            </label>
        </div>
    <?php endif; ?>

    <div class="form-actions">
        <button class="btn primary"><?= $isEdit ? 'Save changes' : 'Register item' ?></button>
    </div>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
