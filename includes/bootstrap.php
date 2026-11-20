<?php
/**
 * bootstrap.php
 * Loaded at the top of every page. Starts the session and imports
 * the config, helper functions and the database connection.
 */

session_start(); // gives us $_SESSION (logged-in user, flash messages)

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';        // connects to MySQL -> $conn
