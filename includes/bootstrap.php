<?php
/**
 * bootstrap.php
 * Loaded at the top of every page. Imports the config, helper functions
 * and the database connection, then starts the session.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';        // connects to MySQL -> $conn
require_once __DIR__ . '/session.php';   // sessions are stored in MySQL

session_start(); // gives us $_SESSION (logged-in user, flash messages)
