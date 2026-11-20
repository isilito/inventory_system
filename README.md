# PartsBin — Small Shop Hardware Inventory

A stock management system for a small PC repair / custom build shop.
Plain PHP 8 + MySQL (XAMPP). No framework.

## Running it on XAMPP

1. Copy this folder into `C:\xampp\htdocs\partsbin`
2. Open the **XAMPP Control Panel** and **Start** both **Apache** and **MySQL**
3. Open <http://localhost/partsbin/>

On the first visit the app creates the `partsbin` database, builds the tables
from `database.sql` and fills them with sample products, pictures and stock history.

**Demo login:** `admin` / `admin123` — or click *Sign up* to make your own account.

Database settings (host, user, password, database name) are at the top of `config.php`.
XAMPP's defaults (`root`, no password) work out of the box.

**Start over:** in phpMyAdmin drop the `partsbin` database, delete the files inside
`uploads/products/` and `uploads/avatars/`, then reload the page.

## Features

| Feature | Where |
|---|---|
| Login / Sign up / Log out | `login.php`, `signup.php`, `logout.php` — passwords stored with `password_hash()` |
| My profile | `profile.php` — change name, email, profile picture and password |
| Dashboard with pictures | `index.php` — your profile picture, totals, product picture gallery, low stock, recent activity |
| Product pictures | upload on `item_form.php`; shown on dashboard, products list, product page, stock table |
| Products (CRUD) | `items.php` (list / search), `item_form.php` (create / edit), `item_view.php` (view / delete) |
| Many variants in one save | `item_form.php` — one product + all its variants in a single submit; variants saved with ONE multi-row `INSERT` |
| Stock table | `stock.php` — every variant in one table (picture, qty, status, value); *Update* opens Restock / Adjust |
| Categories | `categories.php` |
| Restock history | `restocks.php` — filter by date and supplier |
| Valuation | `valuation.php` — value at cost and retail by category |
| AI assistant (Gemini) | floating chat button, bottom right of every page — `chat.php` (back end), `includes/chat.php` (prompt + live inventory), `ai/gemini_chat.py` (Python ↔ Gemini), `includes/chat_widget.php`, `assets/chat.js` |

## Setting up the AI assistant

PHP builds the question (instructions + live inventory) and runs `ai/gemini_chat.py`,
which sends it to Google Gemini and streams the answer back.

1. Install Python 3, then: `pip install google-genai python-dotenv`
2. Copy `.env.example` to `.env` and paste your key from <https://aistudio.google.com/apikey>
   (`.htaccess` blocks browsers from opening `.env`)
3. Set `PYTHON_BIN` at the bottom of `config.php` to your `python.exe`
4. Model and thinking level are in `.env` (`gemini-4-hydrogen` and `gemini-3.5-flash-lite` are free-tier models)

Test the Python part on its own from the project folder:
`echo {"system": "Be brief.", "messages": [{"role": "user", "content": "Hi!"}]} | python ai/gemini_chat.py`

## PHP topics used — where to find them

| Topic | Examples |
|---|---|
| **Superglobals** | `$_POST` (every form), `$_GET` (filters, ids), `$_SESSION` (logged-in user, flash messages), `$_FILES` (picture uploads), `$_SERVER` (`REQUEST_METHOD` in `is_post()`, `PHP_SELF` in `header.php`) |
| **`$_POST`** | `login.php`, `signup.php`, `profile.php`; arrays like `$_POST['variant_label'][]` in `variants_from_post()` |
| **File transfer / manipulation** | `upload_image()` and `delete_image()` in `includes/functions.php` (`move_uploaded_file`, `pathinfo`, `getimagesize`, `mkdir`, `unlink`, `file_exists`); `file_get_contents()` in `db.php`; `copy()` in `seed.php` |
| **Database connection** | `includes/db.php` — `new mysqli()`, `prepare()`, `bind_param()`, `execute()`, `get_result()`, `multi_query()` |
| **Password hashing** | `password_hash()` in `signup.php`, `profile.php`, `seed.php`; `password_verify()` in `login.php`, `profile.php` |
| **Running Python from PHP (chat)** | `chat.php` — `proc_open()`, `fwrite()` / `fgets()` on pipes, `file_get_contents('php://input')`, `json_encode()` / `json_decode()`, `session_write_close()`, `flush()` for streaming |

A full walkthrough of the code is in **PartsBin-Code-Report.pdf**.

## Design and sample photos

- The look follows the [taste-skill](https://github.com/leonxlnx/taste-skill) design system: palette
  "chalk and tomato" (warm off-white, near-black ink, one tomato-red accent), the **Geist** font
  (stored in `assets/fonts/`, so it works offline), one button and card style, and quiet hover/press motion.
- Prices are in pesos (`CURRENCY` in `config.php`).
- The sample products and prices are based on the **PC Express** catalogue (pcx.com.ph). The photos are
  official manufacturer images (Intel, AMD, MSI, Acer, Seagate, AOpen, ASUS, Logitech), listed in
  `assets/samples/SOURCES.md`. They're for this local school demo only. Don't publish or redistribute them.

## File layout

```
config.php                 settings (database, uploads, tags, Python path, chat limits)
.env / .env.example        Gemini API key, model, thinking level (.env is private)
.htaccess                  stops browsers from opening .env
database.sql               table definitions (run automatically on first visit)
chat.php                   chat back end: checks the request, runs Python, streams the answer
ai/gemini_chat.py          Python: asks Google Gemini, prints the answer piece by piece
includes/bootstrap.php     session + imports, loaded by every page
includes/db.php            MySQL connection, query helpers, first-run installer
includes/functions.php     helpers: login, uploads, variants, stock logic
includes/seed.php          sample data + demo account
includes/header.php        layout, navigation, user menu
includes/footer.php        closes the layout, loads the chat widget
includes/variant_rows.php  the "many variants" input table
includes/movements_table.php  shared stock history table
includes/chat.php          live inventory snapshot + instructions for Gemini
includes/chat_widget.php   the floating chat button and panel
assets/                    style.css, app.js, chat.js, placeholder + sample pictures
uploads/products/          uploaded product pictures
uploads/avatars/           uploaded profile pictures
```
