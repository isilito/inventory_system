# 3. Code Questioning Reviewer

> The panel can ask **anyone** about **any** part of the code, with *emphasis on implemented PHP concepts*.
> Every member should know this whole file. Each person still owns one area to know in depth:
>
> | Owner | Area to know in depth | Sections |
> |---|---|---|
> | Member 1 | Login, sign up, sessions, password hashing, profile, file upload | §1, §2.1–2.3, §3.1–3.3 |
> | Member 2 | Database connection, query helpers, dashboard/list SQL, categories | §2.4, §3.4, file 04 |
> | Member 3 | Product form, `$_POST` arrays, multi-row INSERT, product view | §2.1, §3.5–3.6 |
> | Member 4 | Stock logic and weighted average, restocks, valuation, AI chat | §3.7–3.9 |
>
> **When answering:** open the file in VS Code, point at the lines, explain *what* the code does, then *why*.
> References look like `includes/functions.php:352`, meaning file and line number.

---

## §1. How a page request works (know this cold)

Every private page follows the same pattern. Use `stock.php` or `categories.php` as the example:

```
1. require_once 'includes/bootstrap.php'   → session_start(), config, functions, DB connection
2. require_login()                         → not logged in? flash message + redirect to login.php
3. if (is_post()) { ... }                  → handle the submitted form, then redirect()
4. run SELECT queries for the page         → db_rows() / db_row() / db_value()
5. include 'includes/header.php'           → <html>, navigation, flash message
6. HTML + PHP loops print the data         → every value goes through e() (htmlspecialchars)
7. include 'includes/footer.php'           → closes the page, loads JS
```

**Post/Redirect/Get (PRG):** after every successful form submit we call `redirect()` (`header('Location: ...')` + `exit`). Refreshing the page then doesn't resubmit the form, so a restock isn't recorded twice. The success message survives the redirect because it's stored in `$_SESSION['flash']`.

**`bootstrap.php`** (`includes/bootstrap.php:8-12`):
```php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';
```
- `__DIR__` = the folder of the current file, so the paths work no matter which page includes it.
- `require_once` stops with a fatal error if the file is missing and never loads a file twice. `include` (used for `header.php` and `footer.php`) only gives a warning. We use `require` for files the app can't run without.

---

## §2. PHP concepts: what, where, and how to explain them

### 2.1 Superglobals

| Superglobal | What it holds | Where we use it |
|---|---|---|
| `$_POST` | Form fields sent with `method="post"` | `login.php:20-21`, `signup.php:18-22`, `stock.php:19-45`, `item_form.php:37-39` |
| `$_GET` | Values in the URL query string (`?id=5&q=ryzen`) | `items.php:14-17` (filters), `item_view.php:11` (`?id=`), `restocks.php:12-14` |
| `$_SESSION` | Data kept on the server between page loads for one visitor | `functions.php:44` (flash), `:61` (`isset($_SESSION['user_id'])`), `:86-87` (login) |
| `$_FILES` | Uploaded files: `name`, `tmp_name`, `size`, `error`, `type` | `functions.php:105-149` (`upload_image()`) |
| `$_SERVER` | Request/server info | `functions.php:31` (`REQUEST_METHOD`), `header.php:13` (`PHP_SELF`, which highlights the active menu item), `chat.php:49` (`HTTP_X_REQUESTED_WITH`) |

**`$_POST` arrays (good to show).** In `includes/variant_rows.php:39-44` the inputs are named `variant_label[]`, `variant_qty[]` and so on. The `[]` makes PHP collect every row into an **array**:
```php
$_POST['variant_label'] = ['16GB', '32GB', '64GB'];
$_POST['variant_qty']   = ['5', '3', '1'];
```
`variants_from_post()` (`functions.php:265-298`) loops with `for ($i = 0; $i < count($labels); $i++)` and reads the same index `$i` from each array to rebuild one row per variant.

**Hidden `action` input.** One page can have many forms (`item_view.php` has add, edit and delete). Each form sends `<input type="hidden" name="action" value="...">`, and a `switch ($action)` decides what to do (`item_view.php:29-89`, `categories.php:19-42`).

### 2.2 Sessions and login state
- `session_start()` (`bootstrap.php:8`) sends the browser a cookie (`PHPSESSID`) and loads that visitor's `$_SESSION` from the server.
- **Log in** (`functions.php:83-88`): `session_regenerate_id(true)` gives the user a new session ID, which prevents **session fixation**. Then it stores `user_id` and `user_name`.
- **Check** (`functions.php:59-71`): `is_logged_in()` returns `isset($_SESSION['user_id'])`. `require_login()` redirects to `login.php` if that's false. Every private page calls it at the top.
- **Log out** (`logout.php:9-10`): `$_SESSION = []` empties the data, and `session_regenerate_id(true)` throws away the old session ID.
- **Flash messages** (`functions.php:42-53`): `flash()` stores a message. `take_flash()` reads it and `unset()`s it, so it shows exactly once (it's called in `header.php:32`).

### 2.3 Password hashing
- **Sign up** (`signup.php:64`): `password_hash($password, PASSWORD_DEFAULT)` produces a bcrypt hash like `$2y$10$...`. It includes a random **salt**, so the same password gives a different hash every time.
- **Log in** (`login.php:30`): `password_verify($typed, $hashFromDb)` re-hashes the typed password with the salt stored inside the hash and compares the two.
- **Change password** (`profile.php:59-68`): checks the current password with `password_verify()`, then stores the new `password_hash()`.
- Column is `VARCHAR(255)` (`database.sql:13`) because hashes are long and the algorithm may change in future PHP versions.
- **Why not md5/sha1?** They're fast and unsalted, so they can be cracked with lookup tables. bcrypt is deliberately slow and salted.
- Same error for "no user" and "wrong password" (`login.php:36`), so attackers can't find out which usernames exist.

### 2.4 Database connection (mysqli) and prepared statements
`includes/db.php`:
- `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)` (line 12) makes failed queries **throw exceptions** instead of silently returning `false`.
- `new mysqli(DB_HOST, DB_USER, DB_PASS)` (line 16), inside `try { } catch (mysqli_sql_exception $ex)` (lines 14-22). If MySQL isn't started, a friendly message is shown with `die()`.
- `set_charset('utf8mb4')` (line 25) handles the ₱ sign and accented names.
- `CREATE DATABASE IF NOT EXISTS` + `select_db()` (lines 28-29). `SHOW TABLES LIKE 'users'` checks for a first run, and if so `install_database()` runs (lines 32-35).

**The four helpers (lines 47-87). Everything goes through `db_run()`:**
```php
function db_run(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    global $conn;
    $stmt = $conn->prepare($sql);              // 1. SQL with ? placeholders is sent to MySQL
    if ($types !== '') {
        $stmt->bind_param($types, ...$params); // 2. values bound separately; ... = spread operator
    }
    $stmt->execute();                          // 3. run
    return $stmt;
}
```
- `db_rows()` → `get_result()->fetch_all(MYSQLI_ASSOC)`: all rows as associative arrays
- `db_row()` → `fetch_assoc()`: first row or `null`
- `db_value()` → first column of the first row (for `COUNT(*)`, `SUM()`)
- `db_insert_id()` → `$conn->insert_id`: the AUTO_INCREMENT ID of the row just inserted
- **Type string:** one letter per `?`: `i` integer, `d` double/decimal, `s` string. Example: `'idi'` for `[$newQty, $avgCost, $variantId]` (`functions.php:378`).

**Why prepared statements? SQL injection.** The value is never pasted into the SQL text, so input like `' OR 1=1 --` is treated as plain data, not code.

**`global $conn`:** functions can't see variables from outside by default. `global` pulls in the connection created in `db.php`.

**First-run installer** (`db.php:94-114`): `file_get_contents()` reads `database.sql`, then `multi_query()` runs all the `CREATE TABLE` statements at once. A `do { ... } while ($conn->more_results() && $conn->next_result())` loop steps through each result, which is required before the connection can run anything else. Then `seed_sample_data()` adds the demo data.

### 2.5 File upload and file handling
`upload_image()`, `includes/functions.php:102-149`. Explain it step by step:
1. `$_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE` → nothing chosen, not an error (line 105)
2. `UPLOAD_ERR_INI_SIZE` / `UPLOAD_ERR_FORM_SIZE` → too big for PHP's limit (line 112). Any other code that isn't `UPLOAD_ERR_OK` → generic failure (line 115)
3. Our size limit: `$file['size'] > MAX_UPLOAD_SIZE` (2 MB, `config.php:27`)
4. Extension check: `strtolower(pathinfo($name, PATHINFO_EXTENSION))` + `in_array(..., ALLOWED_IMAGE_TYPES)` (lines 124-127)
5. Real-image check: `getimagesize($file['tmp_name']) === false` → not a picture, even if renamed `.jpg` (line 129)
6. `is_dir()` / `mkdir($dir, 0777, true)` creates `uploads/products/` the first time (`true` = create parent folders too)
7. **New unique name**: `$folder . '_' . uniqid() . '.' . $extension` (line 141). Uploads never overwrite each other, and users can't pick a dangerous file name like `../../index.php`
8. `move_uploaded_file($tmp, $target)` (line 144) moves the file out of PHP's temp folder. It only works on real uploaded files
9. Returns `[$newName, $error]`, read with `list($image, $err) = upload_image(...)`

**Other file functions:**
- `delete_image()` (`functions.php:152-162`): `basename()` strips any folder path (safety), then `file_exists()` + `unlink()` deletes the file. Used when a picture is replaced, removed, or its product is deleted (`item_form.php:73`, `item_view.php:85`, `profile.php:36`)
- `image_url()` (`functions.php:165-171`): returns a placeholder SVG when the file is missing. `rawurlencode()` makes the URL safe
- `copy()` in `seed.php:140` copies sample photos into `uploads/products/`
- `file_get_contents()` in `db.php:99` and `chat.php:62` (`php://input`)
- Form must have `enctype="multipart/form-data"` (`signup.php:98`, `item_form.php:129`), or `$_FILES` stays empty
- `uploads/.htaccess` blocks running any `.php` file inside `uploads/`, so an uploaded script can never execute

### 2.6 Functions, types and scope
- **User-defined functions** with **type declarations** and **return types**: `function change_stock(int $variantId, int $delta, string $type, float $unitCost = 0, ...): string`
- **Default parameter values**: `$unitCost = 0`, `$supplier = ''`
- **Nullable types**: `?array` (`current_user()` may return `null`), `?string $date = null`
- **`void`** return type: `redirect()`, `flash()`, `insert_variants()`
- **Returning several values** as an array + `list()`: `variants_from_post()` returns `[$rows, $errors]`, `upload_image()` returns `[$name, $error]`
- **`global`** in `db_run()`; local scope everywhere else

### 2.7 Arrays and array functions
| Function | Where | Why |
|---|---|---|
| `array_column()` | `item_form.php:59` | pull all `label` values out of the variant rows |
| `array_map('strtolower', ...)` | `item_form.php:59` | make labels lowercase so `16GB` = `16gb` |
| `array_unique()` + `count()` | `item_form.php:60` | duplicate check: fewer unique labels means a duplicate exists |
| `array_merge()` | `item_form.php:52` | combine product errors and variant errors |
| `array_push()` | `items.php:30` | add 4 search parameters at once |
| `implode()` | `items.php:52`, `functions.php:327` | join WHERE conditions with `AND`; join `(?,?...)` groups with `,` |
| `in_array()` | `item_form.php:45`, `functions.php:125` | whitelist checks (status tag, file extension) |
| `array_slice()`, `array_shift()`, `end()` | `chat.php:75-81` | keep the last 20 chat messages; the list must start and end with a user message |
| `array_values()` | `db.php:79` | get the first column value |
| `http_build_query()` | `stock.php:105` | rebuild `q=...&category=...&level=...` so filters are kept after a restock |
| associative arrays | `$menu` in `header.php:22-29`, `$form` in `item_form.php:26-32` | |

### 2.8 Control structures
- `if / elseif / else`: everywhere. `stock_status()` (`functions.php:177-186`) is a clear example
- `switch`: `tag_class()` and `movement_label()` (`functions.php:189-218`); action routing in `item_view.php:31`
- `foreach`: printing table rows; `foreach ($form as $key => $default)` (`item_form.php:37`)
- `for`: `variants_from_post()` (`functions.php:271`)
- `do ... while`: `install_database()` (`db.php:104-109`)
- `while`: reading Python output line by line, `chat.php:123`
- `continue`: skip blank variant rows (`functions.php:283`)
- **Alternative syntax in templates**: `<?php if (...): ?> ... <?php endif; ?>`, `foreach: ... endforeach;`
- **Short echo**: `<?= e($value) ?>` is the same as `<?php echo e($value); ?>`

### 2.9 Operators and handy syntax
- **Null coalescing `??`**: `$_POST['login'] ?? ''` gives `''` if the field wasn't sent, with no "undefined index" warning
- **Ternary / nested ternary**: greeting in `index.php:53`: `$hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening')`
- **Short ternary `?:`**: `$row ?: null` (`db.php:72`), `$p['brand'] ?: 'Uncategorized'`
- **Type casting**: `(int) $_GET['id']` turns any text into a safe integer; `(float)`, `(string)`
- **Strict comparison `===`**: compares value **and** type (`'0' == 0` is true, `'0' === 0` is false)
- **Spread operator `...`**: `bind_param($types, ...$params)` turns an array into separate arguments
- **String concatenation `.` and `.=`**: building `$types .= 'issddi'` and SQL strings
- **Double-quoted interpolation**: `"Variant row $rowNumber needs a label."`
- **Heredoc** `<<<PROMPT ... PROMPT;`: multi-line AI instructions with `{$variables}` (`includes/chat.php:69-81`)

### 2.10 Constants
- `define('DB_HOST', 'localhost')` (`config.php:8-27`) at runtime
- `const STATUS_TAGS = [...]` (`config.php:28-34`): array constants at compile time
- Constants are global (usable inside functions without `global`) and can't be changed

### 2.11 Validation and output security
- `trim()` on all text input
- `preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)` (`signup.php:28`): regular expression, only letters, numbers and underscores, 3 to 30 characters
- `filter_var($email, FILTER_VALIDATE_EMAIL)` (`signup.php:31`)
- `strlen($password) < 6`, `strcasecmp()` (case-insensitive compare, `signup.php:45`)
- **XSS protection**: `e()` = `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` (`functions.php:11-14`) turns `<script>` into harmless `&lt;script&gt;`. It's used on every value printed into HTML
- `nl2br(e($notes))` (`item_view.php:131`): escape first, then turn new lines into `<br>`
- Validation happens on the **server**. HTML `required` / `min="0"` are only for convenience, because users can bypass them

### 2.12 Date, number and string functions
- `date_default_timezone_set('Asia/Manila')` (`config.php:16`)
- `date('M j, Y', strtotime($timestamp))` in `nice_date()`; `date('G')` = hour 0–23; `date('Y-m-d H:i:s')` for MySQL
- `number_format($amount, 2)` in `money()`, which adds the `₱` sign
- `uniqid()`, `ucfirst()`, `strtolower()`, `basename()`, `mb_substr()` (UTF-8-safe cut, `chat.php:71`)
- `json_encode()` / `json_decode()` (`chat.php`)
- `round($avgCost, 2)` (`functions.php:378`), `intdiv()` (`seed.php`), `max(0, ...)` (`item_view.php:60`)

### 2.13 HTTP functions
- `header('Location: ...')` + `exit` = redirect. Nothing may be printed before `header()`, which is why all form handling happens **before** `include header.php`
- `http_response_code(401)` and so on in `chat.php`
- `proc_open()` (`chat.php:103-108`) starts Python with pipes: `fwrite()` sends the question, `fgets()` reads the answer line by line, `flush()` streams each piece to the browser

---

## §3. Key features: logic walkthroughs

### 3.1 Login (`login.php`)
1. Already logged in? → `redirect('index.php')` (line 11)
2. `is_post()` → read `$_POST['login']` and `$_POST['password']`
3. Both required, else error
4. `SELECT * FROM users WHERE username = ? OR email = ?` → you can log in with either one
5. `$user && password_verify(...)` → `log_in_user()` → flash "Welcome back" → dashboard
6. Otherwise one general error message
7. The form refills `value="<?= e($login) ?>"` so the user doesn't retype the username

### 3.2 Sign up (`signup.php`)
Validate (name, username regex, email, password length, match) → check duplicates with one query `WHERE username = ? OR email = ?` → optional avatar via `upload_image('avatar','avatars')` → `password_hash()` → `INSERT INTO users` → `SELECT` the new row by `db_insert_id()` → `log_in_user()` → redirect.
The `$errors` array collects **all** problems so the user sees them together. `if (!$errors)` means "no errors so far".

### 3.3 Profile (`profile.php`)
Two forms on one page, told apart by the hidden `action` (`details` / `password`).
- Email must be unique **except for yourself**: `WHERE email = ? AND id <> ?` (line 26)
- New picture → delete the old file, keep the new name. "Remove" checkbox → delete the file and set `avatar = NULL`
- `$_SESSION['user_name']` is updated so the header shows the new name
- Stats: `COUNT(*)` and `SUM(type = 'restock')` (line 76). In MySQL a comparison is 1 or 0, so `SUM` counts the matches

### 3.4 Dashboard (`index.php`)
- Totals in **one query** (lines 15-21): `COUNT(*)`, `SUM(quantity)`, `SUM(quantity * unit_cost)`, `SUM(quantity <= low_threshold)`
- `COALESCE(x, 0)`: `SUM` of no rows is `NULL`, so COALESCE turns it into 0
- Product gallery (lines 25-37): `LEFT JOIN` so products **without variants** still appear, plus `GROUP BY`, `MIN(sell_price)` for "from ₱…", `ORDER BY created_at DESC LIMIT 8`
- Low stock (lines 40-47): `WHERE v.quantity <= v.low_threshold ORDER BY v.quantity` (emptiest first)
- Recent activity: `movement_list('1', '', [], 6)`. `WHERE 1` is always true, meaning no filter

### 3.5 Product registration with many variants (`item_form.php` + `functions.php`)
**One page for both create and edit:** `$isEdit = $id > 0` (line 15), where `?id=` comes from the URL.
1. Read product fields: `foreach ($form as $key => $default) $form[$key] = trim($_POST[$key] ?? '')`
2. Validate name, plus a status tag whitelist with `in_array($form['status'], STATUS_TAGS)`
3. **Create only:** `list($variantRows, $variantErrors) = variants_from_post()`: skips empty rows, needs a label, no negatives
4. Duplicate label check with `array_map` + `array_column` + `array_unique`
5. Picture upload (replace or remove on edit)
6. `INSERT INTO items ...` → `$newId = db_insert_id()`
7. `insert_variants($newId, $variantRows)`

**`insert_variants()`, the multi-row INSERT** (`functions.php:305-341`). Likely question:
```php
foreach ($rows as $row) {
    $placeholders[] = '(?, ?, ?, 0, ?, ?, ?)';  // one group per variant
    $types         .= 'issddi';                  // 6 type letters per variant
    $params[] = $itemId; $params[] = $row['label']; ...
}
$sql = 'INSERT INTO variants (...) VALUES ' . implode(', ', $placeholders);
// 3 variants → VALUES (?,?,?,0,?,?,?), (?,?,?,0,?,?,?), (?,?,?,0,?,?,?)  with types 'issddiissddiissddi'
db_run($sql, $types, $params);
```
- **Why one INSERT?** One trip to the database instead of N, and it's still a prepared statement.
- Quantity is inserted as **0**. Then each opening quantity goes through `change_stock(..., 'initial', ...)` so it's **logged in the history**.
- `insert_id` after a multi-row insert = the ID of the **first** new row. We read back `WHERE item_id = ? AND id >= ? ORDER BY id` and match them by position (`$rows[$index]`).

### 3.6 Product view (`item_view.php`)
- `switch ($action)`: `add_variants` / `save_variant` / `delete_variant` / `delete_item`
- `UPDATE variants ... WHERE id = ? AND item_id = ?` (line 70): the extra `AND item_id` makes sure the variant really belongs to this product, so a tampered `variant_id` can't edit another product
- Quantity is **not** editable here, so every stock change goes through `change_stock()` and is logged
- Delete product: `delete_image()` removes the picture file, then `DELETE FROM items`. MySQL's `ON DELETE CASCADE` removes the variants and their movements automatically
- Totals: a PHP `foreach` adds up `quantity` and `quantity * unit_cost`

### 3.7 Stock: restock, adjust, weighted average (`stock.php` + `change_stock()`), the most important part
`stock.php:18-54`:
- `restock` → `change_stock($variantId, $qty, 'restock', $cost, $supplier, $reference)`
- `adjust` → `$delta = direction === 'remove' ? -$qty : $qty` → `change_stock($variantId, $delta, 'adjust', 0, '', $reason . ' — ' . $note)`
- Returns `''` on success or an error message, shown as a flash. Then it redirects back with the same filters (`$_POST['back']`)

`change_stock()`, `includes/functions.php:352-388`:
```php
$variant = db_row('SELECT * FROM variants WHERE id = ?', 'i', [$variantId]);
if ($variant === null) return 'Part variant not found.';
if ($delta === 0)      return 'Quantity must be greater than zero.';

$oldQty  = (int) $variant['quantity'];
$newQty  = $oldQty + $delta;
$avgCost = (float) $variant['unit_cost'];

if ($newQty < 0) return "Not enough stock — only $oldQty on hand.";   // no negative stock

// Weighted average cost
if ($type === 'restock' && $newQty > 0) {
    $avgCost = (($oldQty * $avgCost) + ($delta * $unitCost)) / $newQty;
} else {
    $unitCost = $avgCost;          // adjustments are logged at the current average cost
}

UPDATE variants SET quantity = ?, unit_cost = ? WHERE id = ?          // new stock + cost
INSERT INTO stock_movements (variant_id, user_id, type, qty_change, qty_after, unit_cost, supplier, reason, created_at)
```
**Weighted average example (the one from the demo):** 4 units at ₱10,100, then receive 2 at ₱10,400
→ (4 × 10,100 + 2 × 10,400) ÷ 6 = (40,400 + 20,800) ÷ 6 = **₱10,200**.
**Why?** The same part bought at different prices: the average gives the real value of what's on the shelf.

`$_SESSION['user_id'] ?? null` records **who** made the change. It's `null` during the sample-data seed, so it shows as "system".

Stock status (`functions.php:177-186`): `qty <= 0` → Out of stock, `qty <= threshold` → Low stock, otherwise In stock.

### 3.8 Restocks, valuation and categories
- **Restocks** (`restocks.php`): builds `$where` starting from `"m.type = 'restock'"`, adding `DATE(m.created_at) >= ?`, `<= ?`, `m.supplier LIKE ?` only for filled filters → `movement_list($where, $types, $params, 500)`. Totals are added up in PHP.
- **Valuation** (`valuation.php:14-25`): `SUM(quantity * unit_cost)` and `SUM(quantity * sell_price)`, `GROUP BY COALESCE(c.name, 'Uncategorized')`. Profit = retail − cost. Margin = profit ÷ retail × 100. The share bar width is `round($share, 1)%`.
- **Categories** (`categories.php`): the same `save` action does both **insert** (`id = 0`) and **update** (`id > 0`). Duplicate check is `WHERE name = ? AND id <> ?`. Delete leaves the products in place because the FK is `ON DELETE SET NULL`. The list uses `COUNT(DISTINCT i.id)`, because the join with variants repeats each product once per variant.

### 3.9 AI assistant (`chat.php`, `includes/chat.php`, `ai/gemini_chat.py`, `assets/chat.js`)
1. The browser (`chat.js`) sends `fetch('chat.php', {method:'POST', body: JSON})` with the header `X-Requested-With: PartsBin`
2. `chat.php` checks: POST only, logged in, header present (stops other websites from using a logged-in browser)
3. `session_write_close()`: releases the session lock so other pages still load while the answer streams
4. `json_decode(file_get_contents('php://input'), true)` reads the JSON body. Only `user`/`assistant` roles are kept, messages are cut to 2,000 characters, and only the last 20 are kept
5. `chat_inventory_snapshot()` runs a SELECT of all variants + the 15 latest movements and turns them into text lines. `chat_system_prompt()` (heredoc) adds the instructions
6. `proc_open()` runs `python ai/gemini_chat.py`, the question is written to its input, and each JSON line of its answer is forwarded with `echo` + `flush()` (streaming, NDJSON)
7. The Python script reads the API key from `.env` (protected by `.htaccess`) and calls Gemini
- **Why Python?** Google's official Gemini SDK is in Python. PHP only builds the prompt and passes the data through.
- **Read-only:** the AI gets a text copy of the data and has no database access, so it can't change anything.

---

## §4. Likely questions and model answers

**General**
1. **What is the flow of your system?** → Log in → dashboard → manage categories and products (with variants) → update stock through restocks and adjustments → view history and valuation. Every change is logged in `stock_movements`.
2. **Why no framework?** → Plain PHP shows the core concepts directly (superglobals, sessions, mysqli), which is what the course covers. Shared code lives in `includes/`.
3. **How do pages share the header and menu?** → `include 'includes/header.php'` / `footer.php`. Each page sets `$pageTitle` first.

**PHP concepts**

4. **Difference between `$_GET` and `$_POST`?** → GET values are in the URL (bookmarkable, used for filters and IDs). POST values are in the request body (used for changing data and passwords). We never change data with GET.
5. **What does `session_start()` do?** → It starts or resumes the visitor's session (cookie `PHPSESSID`) so `$_SESSION` keeps values like `user_id` between pages.
6. **How do you protect private pages?** → `require_login()` at the top of each one checks `$_SESSION['user_id']` and redirects to login if it's missing.
7. **`include` vs `require` vs `_once`?** → `require` stops with a fatal error if the file is missing, `include` only warns. `_once` never loads the same file twice, which prevents "function already declared" errors.
8. **What is `??`?** → Null coalescing. It uses the right side if the left side is missing or null, so `$_POST['x'] ?? ''` gives no warning when the field wasn't sent.
9. **Why `(int) $_GET['id']`?** → It forces a whole number. Text like `5abc` becomes `5`, and garbage becomes `0`, which we treat as "not found".
10. **What is `htmlspecialchars` / your `e()` function for?** → It prevents XSS. User text like `<script>` is printed as text instead of running as code.
11. **What does `list()` do?** → It unpacks an array into variables: `list($name, $error) = upload_image(...)`.
12. **What is the `...` in `bind_param($types, ...$params)`?** → The spread operator. `bind_param` wants separate arguments, so `...` expands the array into them. That lets one helper handle any number of parameters.
13. **Why `global $conn`?** → Variables created outside a function aren't visible inside it, so `global` imports the connection object.
14. **What is a heredoc?** → A multi-line string `<<<NAME ... NAME;` that still fills in `{$variables}`. We use it for the AI prompt.

**Database**

15. **How do you connect to MySQL?** → `new mysqli(host, user, pass)` in `includes/db.php`, then `select_db('partsbin')`. Errors are caught with `try/catch (mysqli_sql_exception)`.
16. **What is a prepared statement and why use it?** → SQL is sent with `?` placeholders, and values are bound separately with `bind_param` and their types. This prevents SQL injection.
17. **What do `i`, `s`, `d` mean?** → integer, string, double (decimal). One letter per `?`.
18. **How is the database created?** → On the first visit, `db.php` runs `CREATE DATABASE IF NOT EXISTS`. If the `users` table is missing, it reads `database.sql` with `file_get_contents` and runs it with `multi_query`, then seeds the sample data.
19. **Relationship between items and variants?** → One-to-many. `variants.item_id` is a foreign key to `items.id` with `ON DELETE CASCADE`.
20. **What happens when a category is deleted?** → `ON DELETE SET NULL`: its products stay but become Uncategorized.
21. **JOIN vs LEFT JOIN?** → `JOIN` keeps only matching rows. `LEFT JOIN` keeps every row from the left table even with no match. We use LEFT JOIN so products with no category or no variants still show up.
22. **Why `GROUP BY`?** → To combine each product's variants into one row with `COUNT`, `SUM` and `MIN`.
23. **WHERE vs HAVING?** → `WHERE` filters rows before grouping. `HAVING` filters after grouping, on totals. The "low stock" filter in `items.php:56-60` uses `HAVING low_count > 0` because `low_count` is a SUM.
24. **What does `COALESCE` do?** → It returns the first non-NULL value. `SUM` of zero rows is NULL, and COALESCE makes it 0.
25. **How does search work?** → `LIKE '%ryzen%'` on name and brand, plus an `EXISTS` subquery that also matches variant labels and SKUs (`items.php:26-27`). The `%` wildcards are added in PHP, and the value is still bound safely.
26. **How do you get the ID of a new row?** → `$conn->insert_id` through `db_insert_id()`.

**Features and logic**

27. **How are passwords stored?** → Only the `password_hash()` result (bcrypt, salted) is stored. `password_verify()` checks them. The real password is never saved.
28. **How does file upload work?** → Error code check → size ≤ 2 MB → extension whitelist → `getimagesize()` proves it's an image → unique name with `uniqid()` → `move_uploaded_file()` into `uploads/<folder>/` → the file name is saved in the database.
29. **Why rename uploaded files?** → So two files named `photo.jpg` don't overwrite each other, and a user can't pick a dangerous name or path.
30. **How do you save many variants in one submit?** → Inputs named `variant_label[]` and so on arrive as arrays. `variants_from_post()` rebuilds the rows, and `insert_variants()` builds one multi-row INSERT with a `(?,?,?,0,?,?,?)` group per row.
31. **How is the average cost computed?** → Weighted average on restock: (old qty × old cost + new qty × new cost) ÷ total qty (`functions.php:371-376`).
32. **What stops stock from going negative?** → `change_stock()` checks `if ($newQty < 0)` and returns an error before any update.
33. **How do you know who changed the stock?** → `stock_movements.user_id` is saved from `$_SESSION['user_id']`. The history joins `users` to show the username.
34. **Why can't quantity be edited directly on the variant form?** → So every change goes through `change_stock()` and leaves an audit trail.
35. **How does the low-stock alert work?** → Each variant has `low_threshold`. `quantity <= low_threshold` means Low, `quantity <= 0` means Out (`stock_status()`).
36. **What is the flash message?** → A one-time message in `$_SESSION['flash']`, shown after a redirect and then removed with `unset()`.
37. **Why redirect after saving?** → Post/Redirect/Get, so refreshing doesn't submit the form again (no double restocks).
38. **What does the AI actually receive?** → A text table of the current inventory plus the latest 15 movements, built from a SELECT every time someone asks. It can't write to the database.

**Tricky ones (answer honestly and confidently)**

39. **Is the restock UPDATE + INSERT in a transaction?** → No, they're two separate queries. For a small single-shop system it works. An improvement would be `$conn->begin_transaction()` … `commit()`, with `SELECT ... FOR UPDATE`, so both happen together and two users can't restock the same variant at the same moment.
40. **Do you have CSRF protection?** → The AI endpoint checks a custom header that other sites can't send. The regular forms don't have CSRF tokens yet. That would be the next improvement: a random token in `$_SESSION` plus a hidden input, compared on submit.
41. **Are there user roles (admin vs staff)?** → Not yet. Every logged-in account has the same access. A `role` column on `users` plus a check in `require_login()` would add that.
42. **`movement_list()` puts `$where` directly into the SQL. Isn't that injection?** → `$where` is only ever built by our own code from fixed strings like `'m.variant_id = ?'`. User values always go in `$params` and are bound. `$limit` is an `int` parameter, so it can only be a number.
43. **What are `includes/store.php` and `data/inventory.php`?** → *(See the note at the bottom of this file. Decide your answer before the presentation.)*

---

## §5. Where is…? (fast lookup during questioning)

| They ask about | Open |
|---|---|
| DB connection | `includes/db.php:14-35` |
| Prepared statement helper | `includes/db.php:47-58` |
| Login check | `login.php:18-39` |
| Password hash | `signup.php:64`, `profile.php:68` |
| Session start / login / logout | `includes/bootstrap.php:8`, `includes/functions.php:83-88`, `logout.php` |
| Protecting pages | `includes/functions.php:65-71` |
| File upload | `includes/functions.php:102-149` |
| Delete file | `includes/functions.php:152-162` |
| `$_POST` arrays | `includes/variant_rows.php:39-44`, `includes/functions.php:265-298` |
| Multi-row INSERT | `includes/functions.php:305-341` |
| Weighted average / stock change | `includes/functions.php:352-388` |
| Search + filters | `items.php:14-61`, `stock.php:57-93` |
| Valuation SQL | `valuation.php:14-25` |
| Tables | `database.sql` |
| Settings / constants | `config.php` |
| AI | `chat.php`, `includes/chat.php` |

---

> **⚠ Note for the group: `includes/store.php` and `data/inventory.php`**
> These two files are left over from an older version that saved data in a PHP file instead of MySQL. **No page uses them anymore** (nothing includes `store.php`, and the `DATA_FILE` constant it needs isn't defined). Before the presentation, either **delete both files** so the panel doesn't ask, or be ready to say: *"That's our first prototype, which stored data in a file with `var_export()`. We moved to MySQL, and the file is no longer included anywhere."*
