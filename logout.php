<?php
/**
 * logout.php — Log out
 * Empties the session, throws away the old session id and goes back to login.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$_SESSION = [];                   // forget everything stored for this visitor (user id, name...)
session_regenerate_id(true);      // give the browser a brand-new session id and delete the old session

flash('You have been logged out.');
redirect('login.php');
