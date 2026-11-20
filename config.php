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
// The API key and model are in the .env file (see .env.example).

// Full path to python.exe. A full path works even when Apache doesn't
// know your PATH. Find yours with:  python -c "import sys; print(sys.executable)"
define('PYTHON_BIN', 'C:\\Users\\Sunny\\AppData\\Local\\Programs\\Python\\Python313\\python.exe');

// Limits that keep each request small (and your bill predictable)
define('CHAT_MAX_TURNS', 20);       // only the last 20 messages are sent to Gemini
define('CHAT_MAX_CHARS', 2000);     // longest message a user may type
