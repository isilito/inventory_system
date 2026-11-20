<?php
/**
 * db.php
 * Connects to MySQL with mysqli and provides small query helpers.
 *
 * On the very first run it also creates the `partsbin` database,
 * builds the tables from database.sql and fills them with sample data.
 */

// Make mysqli throw an error (exception) whenever a query fails,
// instead of silently returning false.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // 1) Connect to the MySQL server (no database selected yet)
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
} catch (mysqli_sql_exception $ex) {
    // The most common cause on XAMPP: MySQL isn't started
    die('<h2>Cannot connect to MySQL</h2>'
        . '<p>Open the XAMPP Control Panel and click <b>Start</b> next to MySQL, then reload this page.</p>'
        . '<p><small>' . htmlspecialchars($ex->getMessage()) . '</small></p>');
}

// Use UTF-8 so names like "Café" or "₱" are stored correctly
$conn->set_charset('utf8mb4');

// 2) Create the database if it doesn't exist yet, then select it
$conn->query('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$conn->select_db(DB_NAME);

// 3) First run? (no `users` table yet) -> build tables and add sample data
$check = $conn->query("SHOW TABLES LIKE 'users'");
if ($check->num_rows === 0) {
    install_database();
}


/* ======================================================================
   Query helpers — every query that uses user input goes through a
   PREPARED STATEMENT, so values are never pasted into the SQL text.

   $types is one letter per parameter:  i = integer, d = decimal,
   s = string.  Example:  db_row('SELECT * FROM users WHERE id = ?', 'i', [5]);
   ====================================================================== */

// Runs a query and returns the mysqli_stmt (useful for INSERT/UPDATE/DELETE)
function db_run(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    global $conn;

    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);   // "..." spreads the array into separate arguments
    }
    $stmt->execute();

    return $stmt;
}

// Returns all matching rows as an array of associative arrays
function db_rows(string $sql, string $types = '', array $params = []): array
{
    $result = db_run($sql, $types, $params)->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Returns the first matching row, or null if nothing matched
function db_row(string $sql, string $types = '', array $params = []): ?array
{
    $result = db_run($sql, $types, $params)->get_result();
    $row = $result->fetch_assoc();
    return $row ?: null;
}

// Returns a single value (e.g. a COUNT or SUM)
function db_value(string $sql, string $types = '', array $params = [])
{
    $row = db_row($sql, $types, $params);
    return $row ? array_values($row)[0] : null;
}

// ID of the last row inserted on this connection
function db_insert_id(): int
{
    global $conn;
    return (int) $conn->insert_id;
}


/* ======================================================================
   First-run installer
   ====================================================================== */

function install_database(): void
{
    global $conn;

    // Read the SQL file from disk and run every statement in it
    $sql = file_get_contents(__DIR__ . '/../database.sql');
    $conn->multi_query($sql);

    // multi_query() runs statements one after another; we must step
    // through every result before the connection can be used again
    do {
        $result = $conn->store_result();
        if ($result) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());

    // Sample categories, products, pictures and a demo account
    require_once __DIR__ . '/seed.php';
    seed_sample_data();
}
