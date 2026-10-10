<?php
/**
 * bootstrap.php
 * Loaded at the top of every page. Starts the session and imports
 * the config, data store and helper functions.
 */

session_start(); // needed for $_SESSION flash messages

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/functions.php';
