<?php
/**
 * index.php — Dashboard
 * Welcome card with the user's picture, headline numbers,
 * a picture gallery of products and low-stock alerts.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle = 'Dashboard';
$user      = current_user();

// ---------- Headline numbers (calculated by MySQL) ----------
$totals = db_row(
    'SELECT COUNT(*) AS variants,
            COALESCE(SUM(quantity), 0) AS units,
            COALESCE(SUM(quantity * unit_cost), 0) AS cost_value,
            COALESCE(SUM(quantity <= low_threshold), 0) AS low_count
     FROM variants'
);
$itemCount = db_value('SELECT COUNT(*) FROM items');

// ---------- Product gallery: newest products with their stock totals ----------
$products = db_rows(
    'SELECT i.id, i.name, i.brand, i.status, i.image, c.name AS category,
            COUNT(v.id) AS variant_count,
            COALESCE(SUM(v.quantity), 0) AS total_qty,
            MIN(v.sell_price) AS min_price,
            COALESCE(SUM(v.quantity <= v.low_threshold), 0) AS low_variants
     FROM items i
     LEFT JOIN categories c ON c.id = i.category_id
     LEFT JOIN variants v   ON v.item_id = i.id
     GROUP BY i.id, i.name, i.brand, i.status, i.image, c.name, i.created_at
     ORDER BY i.created_at DESC, i.id DESC
     LIMIT 8'
);

// ---------- Variants at or below their low-stock threshold (emptiest first) ----------
$lowStock = db_rows(
    'SELECT v.*, i.name AS item_name, i.image
     FROM variants v
     JOIN items i ON i.id = v.item_id
     WHERE v.quantity <= v.low_threshold
     ORDER BY v.quantity, i.name
     LIMIT 10'
);

$recent = movement_list('1', '', [], 6);

// Greeting depends on the time of day
$hour     = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

include __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <img class="avatar xl" src="<?= e(image_url('avatars', $user['avatar'])) ?>" alt="Profile picture">
    <div class="hero-text">
        <p class="muted small"><?= date('l, F j, Y') ?></p>
        <h1><?= $greeting ?>, <?= e($user['full_name']) ?></h1>
        <p class="muted">
            <?php if ($totals['low_count'] > 0): ?>
                <?= (int) $totals['low_count'] ?> part variant(s) need restocking.
            <?php else: ?>
                Everything is well stocked.
            <?php endif; ?>
        </p>
    </div>
    <div class="hero-actions">
        <a class="btn primary" href="item_form.php">+ New product</a>
        <a class="btn" href="stock.php">Update stock</a>
    </div>
</section>

<section class="stats">
    <div class="stat">
        <span class="stat-label">Products</span>
        <span class="stat-value"><?= (int) $itemCount ?></span>
        <span class="stat-sub"><?= (int) $totals['variants'] ?> variants</span>
    </div>
    <div class="stat">
        <span class="stat-label">Units in stock</span>
        <span class="stat-value"><?= number_format($totals['units']) ?></span>
    </div>
    <div class="stat">
        <span class="stat-label">Inventory value</span>
        <span class="stat-value"><?= money($totals['cost_value']) ?></span>
        <span class="stat-sub">at cost</span>
    </div>
    <div class="stat">
        <span class="stat-label">Needs restock</span>
        <span class="stat-value <?= $totals['low_count'] > 0 ? 'text-low' : '' ?>"><?= (int) $totals['low_count'] ?></span>
        <span class="stat-sub">variants low / out</span>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <h2>Products</h2>
        <a class="small" href="items.php">View all →</a>
    </div>
    <?php if (!$products): ?>
        <p class="empty">No products yet. <a href="item_form.php">Add your first one.</a></p>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $p): ?>
                <?php
                if ($p['variant_count'] == 0) {
                    $stock = ['No variants', 'muted'];
                } elseif ($p['total_qty'] <= 0) {
                    $stock = ['Out of stock', 'out'];
                } elseif ($p['low_variants'] > 0) {
                    $stock = ['Low stock', 'low'];
                } else {
                    $stock = ['In stock', 'ok'];
                }
                ?>
                <a class="product-card" href="item_view.php?id=<?= (int) $p['id'] ?>">
                    <div class="product-img"><img src="<?= e(image_url('products', $p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy"></div>
                    <div class="product-body">
                        <span class="product-brand"><?= e($p['brand'] ?: ($p['category'] ?? 'Uncategorized')) ?></span>
                        <span class="product-name"><?= e($p['name']) ?></span>
                        <?php if ($p['min_price'] !== null): ?>
                            <span class="product-price">
                                <?php if ($p['variant_count'] > 1): ?><span class="muted">from</span><?php endif; ?>
                                <?= money($p['min_price']) ?>
                            </span>
                        <?php endif; ?>
                        <span class="product-foot">
                            <?= badge($stock[0], $stock[1]) ?>
                            <span class="muted"><?= (int) $p['total_qty'] ?> pcs · <?= (int) $p['variant_count'] ?> variant<?= $p['variant_count'] == 1 ? '' : 's' ?></span>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<div class="grid-2">
    <section class="card">
        <h2>Low stock</h2>
        <?php if (!$lowStock): ?>
            <p class="empty">Everything is above its threshold.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Part</th><th class="num">Qty</th><th class="num">Min</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($lowStock as $v): ?>
                        <?php list($label, $class) = stock_status($v['quantity'], $v['low_threshold']); ?>
                        <tr>
                            <td>
                                <div class="part-cell">
                                    <img class="thumb" src="<?= e(image_url('products', $v['image'])) ?>" alt="">
                                    <div>
                                        <a href="item_view.php?id=<?= (int) $v['item_id'] ?>"><?= e($v['item_name']) ?></a>
                                        <div class="muted small"><?= e($v['label']) ?> · <?= badge($label, $class) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="num"><?= (int) $v['quantity'] ?></td>
                            <td class="num muted"><?= (int) $v['low_threshold'] ?></td>
                            <td class="num"><a class="btn small" href="stock.php?variant=<?= (int) $v['id'] ?>#stock-forms">Restock</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Recent activity</h2>
        <?php if (!$recent): ?>
            <p class="empty">No stock movements yet.</p>
        <?php else: ?>
            <ul class="activity">
                <?php foreach ($recent as $m): ?>
                    <li>
                        <span class="<?= $m['qty_change'] > 0 ? 'pos' : 'neg' ?> num-strong"><?= $m['qty_change'] > 0 ? '+' . $m['qty_change'] : $m['qty_change'] ?></span>
                        <span>
                            <a href="item_view.php?id=<?= (int) $m['item_id'] ?>"><?= e($m['item_name']) ?></a>
                            <span class="muted">· <?= e($m['label']) ?></span>
                            <span class="muted small d-block"><?= e(movement_label($m['type'])) ?> · <?= e(nice_date($m['created_at'])) ?><?= $m['username'] ? ' · by ' . e($m['username']) : '' ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
