<?php
/**
 * categories.php — Part categorization
 * Add, rename and delete the categories that items are grouped into.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$pageTitle = 'Categories';

// Handle form submissions
if (is_post()) {
    $action      = $_POST['action'] ?? '';
    $id          = (int) ($_POST['id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    switch ($action) {
        case 'save':
            if ($name === '') {
                flash('Category name is required.', 'error');
                redirect('categories.php' . ($id > 0 ? "?edit=$id" : ''));
            }
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?')
                        ->execute([$name, $description, $id]);
                    flash("Category \"$name\" updated.");
                } else {
                    $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)')
                        ->execute([$name, $description]);
                    flash("Category \"$name\" added.");
                }
            } catch (PDOException $ex) {
                // UNIQUE constraint on name
                flash('A category with that name already exists.', 'error');
            }
            break;

        case 'delete':
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            flash('Category deleted. Its items are now uncategorized.');
            break;
    }

    redirect('categories.php');
}

// Category being edited (if any)
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

$categories = $pdo->query(
    'SELECT c.*,
            COUNT(DISTINCT i.id)                       AS item_count,
            COALESCE(SUM(v.quantity), 0)               AS units,
            COALESCE(SUM(v.quantity * v.unit_cost), 0) AS value
     FROM categories c
     LEFT JOIN items i    ON i.category_id = c.id
     LEFT JOIN variants v ON v.item_id = i.id
     GROUP BY c.id
     ORDER BY c.name'
)->fetchAll();

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
                                <a href="items.php?category=<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a>
                                <?php if ($c['description'] !== ''): ?>
                                    <div class="muted small"><?= e($c['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= (int) $c['item_count'] ?></td>
                            <td class="num"><?= (int) $c['units'] ?></td>
                            <td class="num"><?= money($c['value']) ?></td>
                            <td class="num nowrap">
                                <a class="btn small" href="categories.php?edit=<?= (int) $c['id'] ?>">Edit</a>
                                <form method="post" class="inline" onsubmit="return confirm('Delete this category? Items will become uncategorized.');">
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
