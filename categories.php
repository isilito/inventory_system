<?php
/**
 * categories.php — Part categorization
 * Add, rename and delete the categories that products are grouped into.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle = 'Categories';

// ---------- Handle form submissions ----------
if (is_post()) {
    $action      = $_POST['action'] ?? '';
    $id          = (int) ($_POST['id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    switch ($action) {
        case 'save':
            // Names must be filled in and unique
            $duplicate = db_row('SELECT id FROM categories WHERE name = ? AND id <> ?', 'si', [$name, $id]);

            if ($name === '') {
                flash('Category name is required.', 'error');
            } elseif ($duplicate) {
                flash('A category with that name already exists.', 'error');
            } elseif ($id > 0) {
                db_run('UPDATE categories SET name = ?, description = ? WHERE id = ?', 'ssi', [$name, $description, $id]);
                flash("Category \"$name\" updated.");
            } else {
                db_run('INSERT INTO categories (name, description) VALUES (?, ?)', 'ss', [$name, $description]);
                flash("Category \"$name\" added.");
            }
            break;

        case 'delete':
            // Products in this category become uncategorized (ON DELETE SET NULL)
            db_run('DELETE FROM categories WHERE id = ?', 'i', [$id]);
            flash('Category deleted. Its products are now uncategorized.');
            break;
    }

    redirect('categories.php');
}

// Category being edited (if any)
$editing = isset($_GET['edit']) ? db_row('SELECT * FROM categories WHERE id = ?', 'i', [(int) $_GET['edit']]) : null;

// Product count, units and value per category — one query with LEFT JOINs
$categories = db_rows(
    'SELECT c.id, c.name, c.description,
            COUNT(DISTINCT i.id) AS items,
            COALESCE(SUM(v.quantity), 0) AS units,
            COALESCE(SUM(v.quantity * v.unit_cost), 0) AS value
     FROM categories c
     LEFT JOIN items i    ON i.category_id = c.id
     LEFT JOIN variants v ON v.item_id = i.id
     GROUP BY c.id, c.name, c.description
     ORDER BY c.name'
);

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Categories</h1>
</div>

<div class="grid-side">
    <section class="card">
        <?php if (!$categories): ?>
            <p class="empty">No categories yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Name</th><th class="num">Products</th><th class="num">Units</th><th class="num">Value</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td>
                                <a href="items.php?category=<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a>
                                <?php if ($c['description'] !== ''): ?>
                                    <div class="muted small"><?= e($c['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= (int) $c['items'] ?></td>
                            <td class="num"><?= (int) $c['units'] ?></td>
                            <td class="num"><?= money($c['value']) ?></td>
                            <td class="num nowrap">
                                <a class="btn small" href="categories.php?edit=<?= (int) $c['id'] ?>">Edit</a>
                                <form method="post" class="inline" onsubmit="return confirm('Delete this category? Products will become uncategorized.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button class="btn small danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2><?= $editing ? 'Edit category' : 'Add category' ?></h2>
        <form method="post" class="form">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <label>Name
                <input type="text" name="name" required value="<?= e($editing['name'] ?? '') ?>" placeholder="e.g. Graphics Cards">
            </label>
            <label>Description
                <input type="text" name="description" value="<?= e($editing['description'] ?? '') ?>" placeholder="Optional">
            </label>
            <div class="form-actions">
                <button class="btn primary"><?= $editing ? 'Save changes' : 'Add category' ?></button>
                <?php if ($editing): ?>
                    <a class="btn" href="categories.php">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
