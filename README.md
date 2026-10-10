# PartsBin — Small Shop Hardware Inventory

A simple stock management system for a small PC repair / custom build shop.
Plain PHP 8 + SQLite (no framework, no database setup).

## Running it

**XAMPP:** copy this folder into `C:\xampp\htdocs\partsbin`, start Apache, open
<http://localhost/partsbin/>.

**Or with PHP's built-in server:**

```
C:\xampp\php\php.exe -S localhost:8000
```

then open <http://localhost:8000>.

The database (`data/inventory.sqlite`) is created on the first page load and filled
with sample parts and restock history. Delete that file to start fresh.
Shop name, currency and timezone are set in `config.php`.

## Pages & features

| Feature | Where |
|---|---|
| Dashboard Overview | `index.php` — totals and low-stock alerts |
| Part Categorization | `categories.php` — add / edit / delete categories, filter items by category |
| Item Registration (CRUD) | `item_form.php` (create/edit), `items.php` (list/search), `item_view.php` (view/delete) |
| Variant Tracking | `item_view.php` — each item has variants (capacity, color, model) with their own SKU, qty, cost, price |
| Stock Adjustment | `stock.php` — add/remove stock with a reason; every change is logged |
| Low Stock Thresholds | per-variant "Low-stock at" value; flagged on dashboard, items list and stock page |
| Status Tags | item condition tags (New, Used, Refurbished, Defective, Discontinued) + automatic In stock / Low / Out badges |
| Restock History | `restocks.php` — restocks with supplier, cost and total spent; filter by date/supplier |
| Inventory Valuation | `valuation.php` — value at cost and retail by category, potential profit, most valuable stock |

Restocks update a variant's cost using a weighted average, so valuation stays accurate when supplier prices change.

## PHP requirements — where to find them

| Requirement | Examples |
|---|---|
| **Variables** | everywhere, e.g. `$totalQty`, `$partsPrice`, `$form` in `item_form.php` |
| **Comments** | file header doc-blocks in every file, `//` and `/* */` comments throughout |
| **Operators** | arithmetic `+ - * /` (weighted-average cost in `change_stock()`), assignment `+=` (`valuation.php`), comparison `=== <= >`, logical `&& \|\| !`, ternary `?:`, null-coalescing `??`, concatenation `.` |
| **Import statements** | `require_once` (`includes/bootstrap.php`, top of every page), `include` (header/footer, `includes/movements_table.php`) |
| **Control structures** | `if / elseif / else` (`stock_status()` in `includes/functions.php`), `switch` (`tag_class()`, `categories.php`, `item_view.php`), `foreach` (all tables), `while` (`valuation.php`), `match` (`movement_label()`, `items.php`) |
| **Superglobals** | `$_GET` (filters, ids), `$_POST` (all forms), `$_SESSION` (flash messages), `$_SERVER` (`REQUEST_METHOD` in `is_post()`, `PHP_SELF` in `header.php`) |

## File layout

```
config.php               settings (name, currency, tags, adjustment reasons)
includes/bootstrap.php   session + imports, loaded by every page
includes/db.php          PDO connection, schema, sample data
includes/functions.php   helpers and stock logic
includes/header.php      layout + navigation
includes/footer.php
includes/movements_table.php   shared stock history table
assets/style.css
data/                    SQLite database (auto-created, blocked from web access)
```
