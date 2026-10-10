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

// Timezone used for all timestamps
date_default_timezone_set('UTC');

// Data file - a PHP file that returns an array. Created automatically
// on first run. Delete it to reset the system.
define('DATA_FILE', __DIR__ . '/data/inventory.php');

// Default low-stock threshold for new variants
define('DEFAULT_THRESHOLD', 2);

// Status tags describing an item condition
const STATUS_TAGS = ['New', 'Used', 'Refurbished', 'Defective', 'Discontinued'];

// Reasons offered on the stock adjustment form
const ADJUST_REASONS = ['Count correction', 'Damaged', 'Customer return', 'Internal use', 'Lost / stolen', 'Other'];
