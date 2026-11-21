<?php
/**
 * config.php
 * Global settings for PartsBin.
 *
 * Secrets and server-specific values (database login, Gemini key) are read
 * with env(): first from real environment variables (that's how Vercel passes
 * them), then from the .env file in this folder (XAMPP). Anything missing
 * falls back to the XAMPP default shown below.
 */

// Reads one setting: environment variable first, then the .env file
function env(string $key, string $default = ''): string
{
    static $fromFile = null;

    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    // Read .env once: KEY=value lines, # starts a comment
    if ($fromFile === null) {
        $fromFile = [];
        $path = __DIR__ . '/.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                    continue;
                }
                list($name, $val) = explode('=', $line, 2);
                $fromFile[trim($name)] = trim(trim($val), '"\'');
            }
        }
    }

    return ($fromFile[$key] ?? '') !== '' ? $fromFile[$key] : $default;
}

// ---------- Database (XAMPP defaults: user "root", no password) ----------
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', (int) env('DB_PORT', '3306'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'partsbin'));
// Hosted MySQL (Aiven, TiDB Cloud...) needs an encrypted connection: DB_SSL=true
define('DB_SSL', strtolower(env('DB_SSL', 'false')) === 'true');
define('DB_SSL_CA', env('DB_SSL_CA'));   // optional path to the provider's CA certificate

// ---------- App ----------
define('APP_NAME', 'PartsBin');
define('CURRENCY', '₱');
date_default_timezone_set('Asia/Manila');

// Default low-stock threshold for new variants
define('DEFAULT_THRESHOLD', 2);

// ---------- Picture uploads ----------
// Uploaded pictures are stored in the `images` table (see image.php), so they
// also work on hosts without a permanent disk. Older pictures that already
// exist as files in uploads/ are still served from there.
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');

// Largest picture allowed (2 MB) and the file types we accept
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024);
const ALLOWED_IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// Status tags describing an item's condition
const STATUS_TAGS = ['New', 'Used', 'Refurbished', 'Defective', 'Discontinued'];

// Reasons offered on the stock adjustment form
const ADJUST_REASONS = ['Count correction', 'Damaged', 'Customer return', 'Internal use', 'Lost / stolen', 'Other'];

// ---------- AI assistant (Google Gemini) ----------
// chat.php asks Gemini through includes/gemini.php (plain PHP + curl).
define('GEMINI_API_KEY', env('GEMINI_API_KEY'));
define('GEMINI_MODEL', env('GEMINI_MODEL', 'gemini-3.5-flash-lite'));
define('GEMINI_THINKING', strtoupper(env('GEMINI_THINKING', 'LOW')));   // MINIMAL, LOW, MEDIUM or HIGH

// Limits that keep each request small (and your bill predictable)
define('CHAT_MAX_TURNS', 20);       // only the last 20 messages are sent to Gemini
define('CHAT_MAX_CHARS', 2000);     // longest message a user may type
