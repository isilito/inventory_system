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

/* ---------- Lookups ---------- */

function category_name(?int $categoryId): string
{
    $category = $categoryId ? find_row('categories', $categoryId) : null;
    return $category['name'] ?? 'Uncategorized';
}

// Variants of one item, in the order they were added
function item_variants(int $itemId): array
{
    return array_values(array_filter(rows('variants'), fn($v) => $v['item_id'] === $itemId));
}

/**
 * Every variant with its item's name and category attached,
 * sorted by item name then variant label.
 */
function variant_list(): array
{
    $items = rows('items');
    $list  = [];

    foreach (rows('variants') as $v) {
        $item = $items[$v['item_id']] ?? null;
        if ($item === null) {
            continue;
        }
        $v['item_name']   = $item['name'];
        $v['category_id'] = $item['category_id'];
        $list[] = $v;
    }

    usort($list, fn($a, $b) => [$a['item_name'], $a['label']] <=> [$b['item_name'], $b['label']]);
    return $list;
}

/**
 * Stock movements, newest first, with item/variant names attached.
 * Optionally limited to some variant ids and/or one movement type.
 */
function movement_list(?array $variantIds = null, ?string $type = null): array
{
    $variants = rows('variants');
    $items    = rows('items');
    $list     = [];

    foreach (rows('movements') as $m) {
        if ($variantIds !== null && !in_array($m['variant_id'], $variantIds, true)) {
            continue;
        }
        if ($type !== null && $m['type'] !== $type) {
            continue;
        }
        $variant = $variants[$m['variant_id']] ?? null;
        if ($variant === null) {
            continue;
        }
        $m['label']     = $variant['label'];
        $m['item_id']   = $variant['item_id'];
        $m['item_name'] = $items[$variant['item_id']]['name'] ?? '';
        $list[] = $m;
    }

    usort($list, fn($a, $b) => [$b['created_at'], $b['id']] <=> [$a['created_at'], $a['id']]);
    return $list;
}

/* ---------- Deleting (with clean-up of related rows) ---------- */

function delete_variant(int $variantId): void
{
    foreach (rows('movements') as $m) {
        if ($m['variant_id'] === $variantId) {
            delete_row('movements', $m['id']);
        }
    }
    delete_row('variants', $variantId);
}

function delete_item(int $itemId): void
{
    foreach (item_variants($itemId) as $v) {
        delete_variant($v['id']);
    }
    delete_row('items', $itemId);
}

// Items in a deleted category become uncategorized
function delete_category(int $categoryId): void
{
    foreach (rows('items') as $item) {
        if ($item['category_id'] === $categoryId) {
            update_row('items', $item['id'], ['category_id' => null]);
        }
    }
    delete_row('categories', $categoryId);
}

/* ---------- Stock logic ---------- */

/**
 * Changes a variant's stock by $delta and logs the movement.
 * Restocks also update the variant's cost using a weighted average.
 * Changes are in memory — call save_data() afterwards to keep them.
 *
 * $extra keys: unit_cost, supplier, reason, created_at
 * Returns the new quantity. Throws RuntimeException on invalid changes.
 */
function change_stock(int $variantId, int $delta, string $type, array $extra = []): int
{
    $variant = find_row('variants', $variantId);

    if ($variant === null) {
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

    update_row('variants', $variantId, [
        'quantity'  => $newQty,
        'unit_cost' => round($avgCost, 2),
    ]);

    insert_row('movements', [
        'variant_id' => $variantId,
        'type'       => $type,
        'qty_change' => $delta,
        'qty_after'  => $newQty,
        'unit_cost'  => (float) ($extra['unit_cost'] ?? $variant['unit_cost']),
        'supplier'   => $extra['supplier'] ?? '',
        'reason'     => $extra['reason'] ?? '',
        'created_at' => $extra['created_at'] ?? date('Y-m-d H:i:s'),
    ]);

    return $newQty;
}
