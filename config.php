<?php
/**
 * config.php
 * Global settings for the Small Shop Hardware Inventory.
 * Change these values to fit your shop.
 */

// Name shown in the header and browser tab
define('APP_NAME', 'PartsBin');

// Currency symbol used when formatting money
define('CURRENCY', '$');

// Timezone used for all timestamps (change to your local timezone, e.g. 'Asia/Manila')
date_default_timezone_set('UTC');

// SQLite database file — created automatically (with sample data) on first run.
// Delete this file to reset the system.
define('DB_PATH', __DIR__ . '/data/inventory.sqlite');

// Default low-stock threshold for new variants
define('DEFAULT_THRESHOLD', 2);

// Status tags describing an item's condition
const STATUS_TAGS = ['New', 'Used', 'Refurbished', 'Defective', 'Discontinued'];

// Reasons offered on the stock adjustment form
const ADJUST_REASONS = ['Count correction', 'Damaged', 'Customer return', 'Internal use', 'Lost / stolen', 'Other'];
