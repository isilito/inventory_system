<?php
/**
 * image.php?name=products_xxx.jpg
 * Sends a picture stored in the `images` table (see upload_image()).
 * Pictures were public files in uploads/ before, so no login is needed here.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';   // no session needed for a picture

$name  = basename((string) ($_GET['name'] ?? ''));
$image = $name !== '' ? db_row('SELECT mime, data FROM images WHERE name = ?', 's', [$name]) : null;

// Unknown picture: show the same placeholder as "no picture"
if ($image === null) {
    header('Location: ' . (str_starts_with($name, 'avatars_') ? 'assets/img/no-avatar.svg' : 'assets/img/no-image.svg'));
    exit;
}

header('Content-Type: ' . $image['mime']);
header('Content-Length: ' . strlen($image['data']));
header('X-Content-Type-Options: nosniff');
// Every upload gets a new unique name, so the browser may keep it for a year
header('Cache-Control: public, max-age=31536000, immutable');
echo $image['data'];
