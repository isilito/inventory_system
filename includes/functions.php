<?php
/**
 * functions.php
 * Small helpers shared by every page: output escaping, money formatting,
 * flash messages, status badges and the stock-change logic.
 */

/* ---------- Output & request helpers ---------- */

// Escape text before printing it into HTML
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
function nice_date(?string $timestamp): string
{
    return $timestamp ? date('M j, Y', strtotime($timestamp)) : '';
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// Store a one-time message in the session, shown on the next page load
function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
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
        case 'Used':
            return 'muted';
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
    return match ($type) {
        'initial' => 'Opening',
        'restock' => 'Restock',
        'adjust'  => 'Adjustment',
        default   => ucfirst($type),
    };
}

function badge(string $label, string $class): string
{
    return '<span class="badge ' . e($class) . '">' . e($label) . '</span>';
}

/* ---------- Stock logic ---------- */

/**
 * Changes a variant's stock by $delta and logs the movement.
 * Restocks also update the variant's cost using a weighted average.
 *
 * $extra keys: unit_cost, supplier, reason, created_at
 * Returns the new quantity. Throws RuntimeException on invalid changes.
 */
function change_stock(int $variantId, int $delta, string $type, array $extra = []): int
{
    $pdo = db();

    $stmt = $pdo->prepare('SELECT quantity, unit_cost FROM variants WHERE id = ?');
    $stmt->execute([$variantId]);
    $variant = $stmt->fetch();

    if ($variant === false) {
        throw new RuntimeException('Part variant not found.');
    }
    if ($delta === 0) {
        throw new RuntimeException('Quantity must be greater than zero.');
    }

    $oldQty  = (int) $variant['quantity'];
    $newQty  = $oldQty + $delta;
    $avgCost = (float) $variant['unit_cost'];

    if ($newQty < 0) {
        throw new RuntimeException("Not enough stock — only {$oldQty} on hand.");
    }

    // Weighted average cost: (old units × old cost + new units × new cost) ÷ total units
    if ($type === 'restock' && isset($extra['unit_cost']) && $newQty > 0) {
        $avgCost = (($oldQty * $avgCost) + ($delta * (float) $extra['unit_cost'])) / $newQty;
    }

    $pdo->prepare('UPDATE variants SET quantity = ?, unit_cost = ? WHERE id = ?')
        ->execute([$newQty, round($avgCost, 2), $variantId]);

    $pdo->prepare(
        'INSERT INTO stock_movements
            (variant_id, type, qty_change, qty_after, unit_cost, supplier, reason, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $variantId,
        $type,
        $delta,
        $newQty,
        $extra['unit_cost'] ?? (float) $variant['unit_cost'],
        $extra['supplier'] ?? '',
        $extra['reason'] ?? '',
        $extra['created_at'] ?? date('Y-m-d H:i:s'),
    ]);

    return $newQty;
}
