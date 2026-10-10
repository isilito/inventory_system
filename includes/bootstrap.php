<?php
/**
 * bootstrap.php
 * Loaded at the top of every page. Starts the session and imports
 * the config, database layer and helper functions.
 */

session_start(); // needed for $_SESSION flash messages

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
