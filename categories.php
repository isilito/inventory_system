<?php
/**
 * categories.php - Part categorization
 * Add, rename and delete the categories that items are grouped into.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Categories';

// Handle add / edit / delete submissions, then redirect so a refresh doesn't resubmit
if (is_post()) {
    $action      = $_POST['action'] ?? '';
    $id          = (int) ($_POST['id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    switch ($action) {
        case 'save':
            // Names must be unique (case-insensitive), ignoring the category being edited
            $duplicate = false;
            foreach (rows('categories') as $c) {
                if (strcasecmp($c['name'], $name) === 0 && $c['id'] !== $id) {
                    $duplicate = true;
                }
            }

            if ($name === '') {
                flash('Category name is required.', 'error');
            } elseif ($duplicate) {
                flash('A category with that name already exists.', 'error');
            } elseif ($id > 0) {
                update_row('categories', $id, ['name' => $name, 'description' => $description]);
                save_data();
                flash("Category \"$name\" updated.");
            } else {
                insert_row('categories', ['name' => $name, 'description' => $description]);
                save_data();
                flash("Category \"$name\" added.");
            }
            break;

        case 'delete':
            // Items in the deleted category are kept but become uncategorized
            delete_category($id);
            save_data();
            flash('Category deleted. Its items are now uncategorized.');
            break;
    }

    redirect('categories.php');
}

// Category loaded into the form when editing (?edit=ID)
$editing = isset($_GET['edit']) ? find_row('categories', (int) $_GET['edit']) : null;

// Per-category totals: item count, units and cost value
$stats = [];
foreach (rows('categories') as $id => $c) {
    $stats[$id] = ['items' => 0, 'units' => 0, 'value' => 0.0];
}
foreach (rows('items') as $item) {
    if (isset($stats[$item['category_id']])) {
        $stats[$item['category_id']]['items']++;
    }
}
// Units and value come from variants, since that's where quantity is stored
foreach (variant_list() as $v) {
    if (isset($stats[$v['category_id']])) {
        $stats[$v['category_id']]['units'] += $v['quantity'];
        $stats[$v['category_id']]['value'] += $v['quantity'] * $v['unit_cost'];
    }
}

// Alphabetical for display
$categories = rows('categories');
usort($categories, fn($a, $b) => strcasecmp($a['name'], $b['name']));

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
                    <tr><th>Name</th><th class="num">Items</th><th class="num">Units</th><th class="num">Value</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td>
                                <a href="items.php?category=<?= $c['id'] ?>"><?= e($c['name']) ?></a>
                                <?php if ($c['description'] !== ''): ?>
                                    <div class="muted small"><?= e($c['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= $stats[$c['id']]['items'] ?></td>
                            <td class="num"><?= $stats[$c['id']]['units'] ?></td>
                            <td class="num"><?= money($stats[$c['id']]['value']) ?></td>
                            <td class="num nowrap">
                                <a class="btn small" href="categories.php?edit=<?= $c['id'] ?>">Edit</a>
                                <form method="post" class="inline" onsubmit="return confirm('Delete this category? Items will become uncategorized.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
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

    <!-- One form for both add and edit; a hidden id of 0 means "new" -->
    <section class="card">
        <h2><?= $editing ? 'Edit category' : 'Add category' ?></h2>
        <form method="post" class="form">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">
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