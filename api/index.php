<?php
/**
 * api/index.php — the entry point on Vercel (not used on XAMPP).
 *
 * Vercel runs PHP only inside api/. vercel.json sends every request here
 * (except files in assets/ and uploads/, which are sent as they are), and
 * this file runs the matching page from the project folder:
 *   /            -> index.php
 *   /items.php   -> items.php
 */

$root = dirname(__DIR__);
$page = ltrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
if ($page === '') {
    $page = 'index.php';
}

// Only pages in the project folder may run: never config.php, includes/ or anything else
if (!preg_match('/^[a-z_]+\.php$/', $page) || $page === 'config.php' || !is_file("$root/$page")) {
    http_response_code(404);
    echo 'Page not found.';
    exit;
}

// Make the page believe it was opened directly (header.php uses PHP_SELF for the menu)
$_SERVER['SCRIPT_NAME']     = '/' . $page;
$_SERVER['PHP_SELF']        = '/' . $page;
$_SERVER['SCRIPT_FILENAME'] = "$root/$page";
chdir($root);

require "$root/$page";
