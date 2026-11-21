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

XAMPP's database defaults (`localhost`, `root`, no password, database `partsbin`) work
out of the box. To use another database, set `DB_HOST`, `DB_USER`, ... in `.env` (see `.env.example`).

**Start over:** in phpMyAdmin drop the `partsbin` database, then reload the page.

## Deploying to Vercel

Vercel runs the PHP through the community [vercel-php](https://github.com/vercel-community/php) runtime.
`vercel.json` sends every request to `api/index.php`, which runs the matching page
(`/items.php` → `items.php`). Files in `assets/` and `uploads/` are sent as they are.

Vercel has no MySQL and no permanent disk, so:

- the **database** must be a hosted MySQL, e.g. Aiven for MySQL (free plan) or TiDB Cloud Serverless
- **logins** (sessions) are stored in the `sessions` table: `includes/session.php`
- **uploaded pictures** are stored in the `images` table and sent by `image.php`

Steps:

1. Create a free MySQL database and copy its host, port, user and password
2. On <https://vercel.com/new>, import this GitHub repository (Framework preset: **Other**)
3. Under **Environment Variables** add `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`,
   `DB_SSL=true`, `GEMINI_API_KEY`, `GEMINI_MODEL`, `GEMINI_THINKING` (see `.env.example`)
4. Deploy. On the first visit the app builds the tables and loads the sample data, just like on XAMPP.

To bring your local data instead: export the `partsbin` database in phpMyAdmin and import it
into the hosted database before the first visit.

Test the Vercel setup on your own PC: `php -S localhost:8000 api/index.php` (pictures and CSS
won't load this way, because on Vercel those come from `vercel.json`'s routes).

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
| AI assistant (Gemini) | floating chat button, bottom right of every page — `chat.php` (back end), `includes/chat.php` (prompt + live inventory), `includes/gemini.php` (PHP ↔ Gemini with curl), `includes/chat_widget.php`, `assets/chat.js` |

## Setting up the AI assistant

PHP builds the question (instructions + live inventory), sends it to Google Gemini's
REST API with curl (`includes/gemini.php`) and streams the answer back. No Python needed.

1. Copy `.env.example` to `.env` and paste **your own** key from <https://aistudio.google.com/apikey>
   (free; each teammate can make one). Never commit or share the key — Google disables keys
   it finds online. `.env` is in `.gitignore`, and `.htaccess` blocks browsers from opening it.
   On Vercel, put the key in the project's Environment Variables instead.

Model and thinking level are in `.env`. `gemini-3.5-flash-lite` is fast and free;
`gemini-4-hydrogen` is smarter but often times out on the free tier.

**If the chat shows an error:**

| Message | Fix |
|---|---|
| No API key | Do step 1 |
| Gemini rejected the API key | Make a new key (step 1) |
| Free-tier limit reached | Wait a minute |
| Gemini is having trouble / Could not reach Gemini | Switch `GEMINI_MODEL` to `gemini-3.5-flash-lite`, check the internet |

## PHP topics used — where to find them

| Topic | Examples |
|---|---|
| **Superglobals** | `$_POST` (every form), `$_GET` (filters, ids), `$_SESSION` (logged-in user, flash messages), `$_FILES` (picture uploads), `$_SERVER` (`REQUEST_METHOD` in `is_post()`, `PHP_SELF` in `header.php`) |
| **`$_POST`** | `login.php`, `signup.php`, `profile.php`; arrays like `$_POST['variant_label'][]` in `variants_from_post()` |
| **File transfer / manipulation** | `upload_image()` and `delete_image()` in `includes/functions.php` (`is_uploaded_file`, `pathinfo`, `getimagesize`, `file_get_contents`, `unlink`, `file_exists`); pictures saved in the `images` table and sent by `image.php` (`header()`, `echo`); `file_get_contents()` in `db.php` and `seed.php` |
| **Database connection** | `includes/db.php` — `mysqli_init()`, `real_connect()`, `prepare()`, `bind_param()`, `execute()`, `get_result()`, `multi_query()` |
| **Sessions** | `session_start()` in `bootstrap.php`; stored in MySQL by a `SessionHandlerInterface` class in `includes/session.php` |
| **Password hashing** | `password_hash()` in `signup.php`, `profile.php`, `seed.php`; `password_verify()` in `login.php`, `profile.php` |
| **Calling a web API (chat)** | `includes/gemini.php` — `curl_init()`, `curl_setopt_array()` with `CURLOPT_WRITEFUNCTION` to read the streamed answer; `chat.php` — `file_get_contents('php://input')`, `json_encode()` / `json_decode()`, `session_write_close()`, `flush()` for streaming |

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
config.php                 settings (database, uploads, tags, Gemini, chat limits) + env()
.env / .env.example        API key, model, database login (.env is private, not in git)
.htaccess                  stops browsers from opening .env (XAMPP)
vercel.json / .vercelignore  Vercel: PHP runtime, routes, files not to upload
api/index.php              Vercel entry point: runs the requested page
database.sql               table definitions (run automatically on first visit)
chat.php                   chat back end: checks the request, asks Gemini, streams the answer
image.php                  sends a picture stored in the `images` table
includes/gemini.php        asks Google Gemini with curl, reads the answer piece by piece
includes/bootstrap.php     imports + session, loaded by every page
includes/session.php       stores sessions in MySQL (needed on Vercel)
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
uploads/products/          sample product pictures (new uploads go in the `images` table)
```
