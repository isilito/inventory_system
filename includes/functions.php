<?php
/**
 * functions.php
 * Helpers shared by every page: output escaping, money formatting,
 * login checks, flash messages, picture uploads and the stock logic.
 */

/* ---------- Output & request helpers ---------- */

// Escape text before printing it into HTML (stops <script> injection)
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Format a number as money, e.g. $1,234.50
function money($amount): string
{
    return CURRENCY . number_format((float) $amount, 2);
}

// Short readable date, e.g. "Oct 10, 2026"
function nice_date($timestamp): string
{
    return $timestamp ? date('M j, Y', strtotime($timestamp)) : '';
}

// Was this page requested by submitting a form with method="post"?
function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// Send the browser to another page and stop this script
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// Store a one-time message in the session, shown on the next page load
function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

// Read the flash message (if any) and remove it so it only shows once
function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}


/* ---------- Login / accounts ---------- */

// Is somebody logged in right now?
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

// Put this at the top of every private page
function require_login(): void
{
    if (!is_logged_in()) {
        flash('Please log in first.', 'error');
        redirect('login.php');
    }
}

// The logged-in user's row from the database (or null)
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return db_row('SELECT id, full_name, username, email, avatar, created_at FROM users WHERE id = ?', 'i', [$_SESSION['user_id']]);
}

// Remember the user in the session after a successful login / signup
function log_in_user(array $user): void
{
    session_regenerate_id(true);           // new session id = protection against session fixation
    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
}


/* ---------- Picture uploads (file transfer) ---------- */

/**
 * Saves a picture sent through <input type="file" name="$field">.
 *
 * Returns an array with two values:
 *   [0] the new file name ('' if no file was chosen)
 *   [1] an error message   ('' if everything went fine)
 *
 * $folder is a sub-folder of uploads/, e.g. 'products' or 'avatars'.
 */
function upload_image(string $field, string $folder): array
{
    // Nothing chosen in the file input -> not an error, just no picture
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['', ''];
    }

    $file = $_FILES[$field];

    // PHP reports its own upload problems through the 'error' code
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['', 'The picture is too large.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['', 'The picture could not be uploaded (error code ' . $file['error'] . ').'];
    }

    // Our own checks: size, extension, and that it really is an image
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['', 'The picture must be 2 MB or smaller.'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_IMAGE_TYPES)) {
        return ['', 'Only JPG, PNG, GIF or WEBP pictures are allowed.'];
    }

    // getimagesize() reads the picture's header: false means it isn't a real image
    $info = is_uploaded_file($file['tmp_name']) ? getimagesize($file['tmp_name']) : false;
    if ($info === false) {
        return ['', 'That file is not a valid picture.'];
    }

    // Give the picture a new unique name so uploads never overwrite each other
    // and a user can't choose a dangerous file name
    $newName = $folder . '_' . uniqid() . '.' . $extension;

    // Save the picture's bytes in the `images` table (works on any host, even without a disk)
    save_image($newName, $info['mime'], file_get_contents($file['tmp_name']));

    return [$newName, ''];
}

// Stores a picture in the database under $name
function save_image(string $name, string $mime, string $bytes): void
{
    db_run('REPLACE INTO images (name, mime, data) VALUES (?, ?, ?)', 'sss', [$name, $mime, $bytes]);
}

// Deletes a picture (used when replacing or deleting): from the database,
// and from uploads/ for pictures saved as files by older versions
function delete_image(string $folder, ?string $fileName): void
{
    if ($fileName === null || $fileName === '') {
        return;
    }

    db_run('DELETE FROM images WHERE name = ?', 's', [basename($fileName)]);

    $path = UPLOAD_DIR . $folder . '/' . basename($fileName);
    if (file_exists($path)) {
        @unlink($path);   // @: on Vercel the disk is read-only, which is fine
    }
}

// URL for a picture, or a placeholder image when there is none
function image_url(string $folder, ?string $fileName): string
{
    if ($fileName === null || $fileName === '') {
        return $folder === 'avatars' ? 'assets/img/no-avatar.svg' : 'assets/img/no-image.svg';
    }
    // An older picture saved as a file: let the web server send it directly
    if (file_exists(UPLOAD_DIR . $folder . '/' . $fileName)) {
        return UPLOAD_URL . $folder . '/' . rawurlencode($fileName);
    }
    return 'image.php?name=' . rawurlencode($fileName);
}


/* ---------- Status tags & badges ---------- */

// Returns [label, css class] for a stock level compared to its threshold
function stock_status(int $qty, int $threshold): array
{
    if ($qty <= 0) {
        return ['Out of stock', 'out'];
    } elseif ($qty <= $threshold) {
        return ['Low stock', 'low'];
    } else {
        return ['In stock', 'ok'];
    }
}

// CSS class for an item's condition tag
function tag_class(string $tag): string
{
    switch ($tag) {
        case 'New':
            return 'ok';
        case 'Refurbished':
            return 'info';
        case 'Defective':
            return 'out';
        case 'Discontinued':
            return 'low';
        default:
            return 'muted';
    }
}

// Human label for a stock movement type
function movement_label(string $type): string
{
    switch ($type) {
        case 'initial':
            return 'Opening';
        case 'restock':
            return 'Restock';
        case 'adjust':
            return 'Adjustment';
        default:
            return ucfirst($type);
    }
}

function badge(string $label, string $class): string
{
    return '<span class="badge ' . e($class) . '">' . e($label) . '</span>';
}


/* ---------- Lookups ---------- */

function all_categories(): array
{
    return db_rows('SELECT * FROM categories ORDER BY name');
}

// Variants of one item, in the order they were added
function item_variants(int $itemId): array
{
    return db_rows('SELECT * FROM variants WHERE item_id = ? ORDER BY id', 'i', [$itemId]);
}

// Stock movements with item / variant names and the user who made them
function movement_list(string $where = '1', string $types = '', array $params = [], int $limit = 20): array
{
    $sql = "SELECT m.*, v.label, v.item_id, i.name AS item_name, u.username
            FROM stock_movements m
            JOIN variants v ON v.id = m.variant_id
            JOIN items i    ON i.id = v.item_id
            LEFT JOIN users u ON u.id = m.user_id
            WHERE $where
            ORDER BY m.created_at DESC, m.id DESC
            LIMIT $limit";

    return db_rows($sql, $types, $params);
}


/* ---------- Variants: read many rows from $_POST, insert them at once ---------- */

/**
 * The item form sends variant fields as arrays, one entry per row:
 *   $_POST['variant_label'] = ['8GB', '16GB']
 *   $_POST['variant_qty']   = ['10',  '5'  ]   ...and so on.
 *
 * This turns them into a list of rows and collects validation errors.
 * Completely empty rows are ignored. Returns [rows, errors].
 */
function variants_from_post(): array
{
    $labels = $_POST['variant_label'] ?? [];
    $rows   = [];
    $errors = [];

    for ($i = 0; $i < count($labels); $i++) {
        $row = [
            'label' => trim($labels[$i]),
            'sku'   => trim($_POST['variant_sku'][$i] ?? ''),
            'qty'   => trim($_POST['variant_qty'][$i] ?? ''),
            'cost'  => trim($_POST['variant_cost'][$i] ?? ''),
            'price' => trim($_POST['variant_price'][$i] ?? ''),
            'min'   => trim($_POST['variant_min'][$i] ?? ''),
        ];

        // Skip rows the user left blank
        if ($row['label'] === '' && $row['sku'] === '' && $row['cost'] === '' && $row['price'] === '' && ($row['qty'] === '' || $row['qty'] === '0')) {
            continue;
        }

        $rowNumber = $i + 1;
        if ($row['label'] === '') {
            $errors[] = "Variant row $rowNumber needs a label.";
        }
        if ((int) $row['qty'] < 0 || (float) $row['cost'] < 0 || (float) $row['price'] < 0 || (int) $row['min'] < 0) {
            $errors[] = "Variant row $rowNumber: quantity, cost, price and threshold cannot be negative.";
        }

        $rows[] = $row;
    }

    return [$rows, $errors];
}

/**
 * Inserts ALL variants of an item with ONE multi-row INSERT statement:
 *   INSERT INTO variants (...) VALUES (?,?,?,...), (?,?,?,...), ...
 * then records each variant's opening quantity in the stock history.
 */
function insert_variants(int $itemId, array $rows): void
{
    if (count($rows) === 0) {
        return;
    }

    $placeholders = [];
    $types        = '';
    $params       = [];

    foreach ($rows as $row) {
        $placeholders[] = '(?, ?, ?, 0, ?, ?, ?)';
        $types         .= 'issddi';
        $params[] = $itemId;
        $params[] = $row['label'];
        $params[] = $row['sku'];
        $params[] = (float) $row['cost'];
        $params[] = (float) $row['price'];
        $params[] = $row['min'] === '' ? DEFAULT_THRESHOLD : (int) $row['min'];
    }

    $sql = 'INSERT INTO variants (item_id, label, sku, quantity, unit_cost, sell_price, low_threshold) VALUES '
         . implode(', ', $placeholders);
    db_run($sql, $types, $params);

    // For a multi-row INSERT, insert_id is the id of the FIRST new row.
    // Read back the new rows (same order) to log their opening stock.
    $firstId = db_insert_id();
    $newRows = db_rows('SELECT id FROM variants WHERE item_id = ? AND id >= ? ORDER BY id', 'ii', [$itemId, $firstId]);

    foreach ($newRows as $index => $variant) {
        $qty = (int) $rows[$index]['qty'];
        if ($qty > 0) {
            change_stock($variant['id'], $qty, 'initial', 0, '', 'Opening stock count');
        }
    }
}


/* ---------- Stock logic ---------- */

/**
 * Changes a variant's stock by $delta and logs the movement.
 * Restocks also update the variant's cost using a weighted average.
 *
 * Returns '' on success, or an error message.
 */
function change_stock(int $variantId, int $delta, string $type, float $unitCost = 0, string $supplier = '', string $reason = '', ?string $date = null): string
{
    $variant = db_row('SELECT * FROM variants WHERE id = ?', 'i', [$variantId]);

    if ($variant === null) {
        return 'Part variant not found.';
    }
    if ($delta === 0) {
        return 'Quantity must be greater than zero.';
    }

    $oldQty  = (int) $variant['quantity'];
    $newQty  = $oldQty + $delta;
    $avgCost = (float) $variant['unit_cost'];

    if ($newQty < 0) {
        return "Not enough stock — only $oldQty on hand.";
    }

    // Weighted average cost: (old units × old cost + new units × new cost) ÷ total units
    if ($type === 'restock' && $newQty > 0) {
        $avgCost = (($oldQty * $avgCost) + ($delta * $unitCost)) / $newQty;
    } else {
        $unitCost = $avgCost;   // non-restock movements are logged at the current cost
    }

    db_run('UPDATE variants SET quantity = ?, unit_cost = ? WHERE id = ?', 'idi', [$newQty, round($avgCost, 2), $variantId]);

    db_run(
        'INSERT INTO stock_movements (variant_id, user_id, type, qty_change, qty_after, unit_cost, supplier, reason, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'iisiidsss',
        [$variantId, $_SESSION['user_id'] ?? null, $type, $delta, $newQty, $unitCost, $supplier, $reason, $date ?? date('Y-m-d H:i:s')]
    );

    return '';
}
