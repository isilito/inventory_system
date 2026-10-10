<?php
/**
 * item_form.php — Item registration (create & edit)
 * New items are created together with their first variant.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$pdo    = db();
$id     = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
$errors = [];

// Load the item when editing
$item = null;
if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = ?');
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if (!$item) {
        flash('Item not found.', 'error');
        redirect('items.php');
    }
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
        $categoryId = $form['category_id'] !== '' ? (int) $form['category_id'] : null;

        if ($isEdit) {
            $pdo->prepare('UPDATE items SET name = ?, brand = ?, category_id = ?, status = ?, notes = ? WHERE id = ?')
                ->execute([$form['name'], $form['brand'], $categoryId, $form['status'], $form['notes'], $id]);
            flash('Item updated.');
            redirect("item_view.php?id=$id");
        }

        // Create item + first variant in one transaction
        $pdo->beginTransaction();

        $pdo->prepare('INSERT INTO items (name, brand, category_id, status, notes, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$form['name'], $form['brand'], $categoryId, $form['status'], $form['notes'], date('Y-m-d H:i:s')]);
        $newId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO variants (item_id, label, sku, quantity, unit_cost, sell_price, low_threshold)
             VALUES (?, ?, ?, 0, ?, ?, ?)'
        )->execute([$newId, $form['label'], $form['sku'], (float) $form['unit_cost'], (float) $form['sell_price'], max(0, (int) $form['low_threshold'])]);
        $variantId = (int) $pdo->lastInsertId();

        // Log opening quantity so it shows in the stock history
        if ((int) $form['quantity'] > 0) {
            change_stock($variantId, (int) $form['quantity'], 'initial', ['reason' => 'Opening stock count']);
        }

        $pdo->commit();

        flash('Item registered.');
        redirect("item_view.php?id=$newId");
    }
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$pageTitle  = $isEdit ? 'Edit item' : 'New item';

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
                    <option value="<?= (int) $c['id'] ?>" <?= (string) $form['category_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
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
