<?php
/**
 * config.php
 * Global settings for PartsBin. Change these values to fit your setup.
 */

// ---------- Database (XAMPP defaults: user "root", no password) ----------
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'partsbin');

// ---------- App ----------
define('APP_NAME', 'PartsBin');
define('CURRENCY', '₱');
date_default_timezone_set('Asia/Manila');

// Default low-stock threshold for new variants
define('DEFAULT_THRESHOLD', 2);

// ---------- Picture uploads ----------
// Folder on disk where uploaded pictures are saved, and the matching URL path
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');

// Largest picture allowed (2 MB) and the file types we accept
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024);
const ALLOWED_IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// Status tags describing an item's condition
const STATUS_TAGS = ['New', 'Used', 'Refurbished', 'Defective', 'Discontinued'];

// Reasons offered on the stock adjustment form
const ADJUST_REASONS = ['Count correction', 'Damaged', 'Customer return', 'Internal use', 'Lost / stolen', 'Other'];

// ---------- AI assistant (Google Gemini, through Python) ----------
// chat.php runs ai/gemini_chat.py, which talks to Gemini.
const PYTHON_BIN_OVERRIDE = '';

/**
 * Looks for python.exe in the usual install folders and returns the newest one.
 * Apache often doesn't know your PATH, so we search the folders directly.
 */
function find_python(): string
{
    if (PYTHON_BIN_OVERRIDE !== '') {
        return PYTHON_BIN_OVERRIDE;
    }

    $patterns = [];
    if (DIRECTORY_SEPARATOR === '\\') {   // Windows
        $local = getenv('LOCALAPPDATA') ?: '';
        if ($local !== '') {
            $patterns[] = $local . '\\Python\\pythoncore-*\\python.exe';      // Python install manager (python.org, 3.14+)
            $patterns[] = $local . '\\Programs\\Python\\Python3*\\python.exe'; // classic python.org installer
        }
        $patterns[] = 'C:\\Program Files\\Python3*\\python.exe';               // "install for all users"
        $patterns[] = 'C:\\Python3*\\python.exe';
        // When Apache runs as a Windows service it has no LOCALAPPDATA, so also check every user folder
        $patterns[] = 'C:\\Users\\*\\AppData\\Local\\Python\\pythoncore-*\\python.exe';
        $patterns[] = 'C:\\Users\\*\\AppData\\Local\\Programs\\Python\\Python3*\\python.exe';
    } else {                              // macOS / Linux
        $patterns = ['/opt/homebrew/bin/python3', '/usr/local/bin/python3', '/usr/bin/python3'];
    }

    foreach ($patterns as $pattern) {
        $found = glob($pattern) ?: [];
        if ($found) {
            usort($found, fn($a, $b) => strnatcasecmp($b, $a));   // newest version first (3.13 before 3.9)
            return $found[0];
        }
    }

    return 'python';   // last resort: hope it's on Apache's PATH
}

define('PYTHON_BIN', find_python());

// Limits that keep each request small (and your bill predictable)
define('CHAT_MAX_TURNS', 20);       // only the last 20 messages are sent to Gemini
define('CHAT_MAX_CHARS', 2000);     // longest message a user may type
