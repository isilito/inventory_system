<?php
/**
 * item_form.php — Product registration (create & edit)
 *
 * Creating: the product, its picture AND all of its variants are saved
 * from ONE form submission — the variants with a single multi-row INSERT.
 * Editing: change the details or replace the picture. Variants are
 * managed on the product page (item_view.php).
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$id     = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
$errors = [];

// Load the product when editing
$item = $isEdit ? db_row('SELECT * FROM items WHERE id = ?', 'i', [$id]) : null;
if ($isEdit && $item === null) {
    flash('Product not found.', 'error');
    redirect('items.php');
}

// Form values: posted data > existing product > defaults
$form = [
    'name'        => $item['name'] ?? '',
    'brand'       => $item['brand'] ?? '',
    'category_id' => $item['category_id'] ?? '',
    'status'      => $item['status'] ?? 'New',
    'notes'       => $item['notes'] ?? '',
];
$variantRows = [];

if (is_post()) {
    // Read the product fields from $_POST
    foreach ($form as $key => $default) {
        $form[$key] = trim($_POST[$key] ?? '');
    }

    // ---------- Validation ----------
    if ($form['name'] === '') {
        $errors[] = 'Product name is required.';
    }
    if (!in_array($form['status'], STATUS_TAGS)) {
        $errors[] = 'Choose a valid status tag.';
    }

    if (!$isEdit) {
        // Read every variant row that was sent as arrays
        list($variantRows, $variantErrors) = variants_from_post();
        $errors = array_merge($errors, $variantErrors);

        if (count($variantRows) === 0) {
            $errors[] = 'Add at least one variant.';
        }

        // No two variants of the same product with the same label
        $labels = array_map('strtolower', array_column($variantRows, 'label'));
        if (count($labels) !== count(array_unique($labels))) {
            $errors[] = 'Each variant needs a different label.';
        }
    }

    // ---------- Picture ($_FILES) ----------
    $image = $item['image'] ?? null;
    if (!$errors) {
        list($newImage, $uploadError) = upload_image('image', 'products');

        if ($uploadError !== '') {
            $errors[] = $uploadError;
        } elseif ($newImage !== '') {
            delete_image('products', $image);   // replacing: remove the old picture file
            $image = $newImage;
        } elseif (isset($_POST['remove_image'])) {
            delete_image('products', $image);
            $image = null;
        }
    }

    // ---------- Save ----------
    if (!$errors) {
        $categoryId = $form['category_id'] !== '' ? (int) $form['category_id'] : null;

        if ($isEdit) {
            db_run(
                'UPDATE items SET category_id = ?, name = ?, brand = ?, status = ?, notes = ?, image = ? WHERE id = ?',
                'isssssi',
                [$categoryId, $form['name'], $form['brand'], $form['status'], $form['notes'], $image, $id]
            );
            flash('Product updated.');
            redirect("item_view.php?id=$id");
        }

        // 1) the product
        db_run(
            'INSERT INTO items (category_id, name, brand, status, notes, image) VALUES (?, ?, ?, ?, ?, ?)',
            'isssss',
            [$categoryId, $form['name'], $form['brand'], $form['status'], $form['notes'], $image]
        );
        $newId = db_insert_id();

        // 2) all its variants in one INSERT (+ opening stock in the history)
        insert_variants($newId, $variantRows);

        flash('Product registered with ' . count($variantRows) . ' variant(s).');
        redirect("item_view.php?id=$newId");
    }
}

$categories = all_categories();

$pageTitle = $isEdit ? 'Edit product' : 'New product';
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

<!-- enctype="multipart/form-data" lets the browser send the picture file -->
<form method="post" enctype="multipart/form-data" class="card form">
    <h2>Product details</h2>
    <div class="form-split">
        <div class="image-pick">
            <img id="image-preview" src="<?= e(image_url('products', $item['image'] ?? null)) ?>" alt="">
            <label>Product picture
                <input type="file" name="image" accept="image/*" data-preview="image-preview">
            </label>
            <span class="muted small">JPG, PNG, GIF or WEBP · max 2 MB</span>
            <?php if (!empty($item['image'])): ?>
                <label class="check"><input type="checkbox" name="remove_image" value="1"> Remove picture</label>
            <?php endif; ?>
        </div>

        <div>
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
                <textarea name="notes" rows="3" placeholder="Optional"><?= e($form['notes']) ?></textarea>
            </label>
        </div>
    </div>

    <?php if (!$isEdit): ?>
        <h2>Variants</h2>
        <p class="muted small">Add every version of this product here — capacity, color, model… They are all saved together with the product.</p>
        <?php include __DIR__ . '/includes/variant_rows.php'; ?>
    <?php endif; ?>

    <div class="form-actions">
        <button class="btn primary"><?= $isEdit ? 'Save changes' : 'Register product' ?></button>
        <?php if ($isEdit): ?><span class="muted small">Variants are edited on the <a href="item_view.php?id=<?= $id ?>">product page</a>.</span><?php endif; ?>
    </div>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
