<?php
/**
 * chat.php (include)
 * Builds what Gemini needs to know before it answers: who it is
 * (the system prompt) and the shop's current stock (read from MySQL).
 *
 * Gemini can't see your database. Instead, every time someone sends a
 * message we turn the inventory into plain text and send it along.
 * PartsBin is small (dozens of variants), so the whole list fits easily.
 */

// The whole inventory as compact text lines, one line per variant
function chat_inventory_snapshot(): string
{
    $rows = db_rows(
        "SELECT i.name, i.brand, i.status, c.name AS category,
                v.label, v.sku, v.quantity, v.low_threshold, v.unit_cost, v.sell_price
         FROM variants v
         JOIN items i           ON i.id = v.item_id
         LEFT JOIN categories c ON c.id = i.category_id
         ORDER BY c.name, i.name, v.id"
    );

    $lines = ['category | product | brand | condition | variant | sku | qty | low-stock at | unit cost | sell price'];
    foreach ($rows as $r) {
        $lines[] = implode(' | ', [
            $r['category'] ?? 'Uncategorized',
            $r['name'],
            $r['brand'],
            $r['status'],
            $r['label'],
            $r['sku'],
            $r['quantity'],
            $r['low_threshold'],
            money($r['unit_cost']),
            money($r['sell_price']),
        ]);
    }

    // The 15 latest stock changes, so Gemini can answer "what did we restock?"
    $moves = movement_list('1', '', [], 15);
    $lines[] = '';
    $lines[] = 'Latest stock changes (newest first): date | type | product | variant | change | qty after | supplier | reason | by';
    foreach ($moves as $m) {
        $lines[] = implode(' | ', [
            nice_date($m['created_at']),
            movement_label($m['type']),
            $m['item_name'],
            $m['label'],
            ($m['qty_change'] > 0 ? '+' : '') . $m['qty_change'],
            $m['qty_after'],
            $m['supplier'],
            $m['reason'],
            $m['username'] ?? '',
        ]);
    }

    return implode("\n", $lines);
}

// The instructions Gemini follows for the whole conversation
function chat_system_prompt(array $user, string $inventory): string
{
    $shop     = APP_NAME;
    $today    = date('l, F j, Y');
    $name     = $user['full_name'];
    $currency = CURRENCY;

    return <<<PROMPT
You are the assistant inside {$shop}, the inventory system of a small PC repair and custom-build shop in the Philippines. You are talking with {$name}, a staff member. Today is {$today}. Prices are in pesos ({$currency}).

Answer questions about the shop's stock using the inventory below. It is read from the database at the moment the question is asked, so it is current. A variant is low on stock when its qty is at or below its "low-stock at" number, and out of stock at 0. Stock value at cost is qty × unit cost; margin is sell price − unit cost.

If the answer isn't in the data, say so plainly instead of guessing. You can read the inventory but you can't change it: when someone wants to restock, adjust or edit something, tell them where to do it (Stock page → Update for restocks and adjustments, Products page to add or edit products, Restocks page for restock history, Valuation page for value by category).

You are in a small chat panel, so keep answers short: a sentence or two, or a short bullet list. Use **bold** for product names or key numbers when it helps. Do the arithmetic carefully. You may also help with general PC hardware questions (compatibility, which part suits a build) and point to matching items in stock.

<inventory>
{$inventory}
</inventory>
PROMPT;
}
