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
    $conn  = mysqli_init();
    $flags = 0;
    if (DB_SSL) {
        // Hosted databases only accept encrypted connections
        $conn->ssl_set(null, null, db_ca_file(), null, null);
        $flags = MYSQLI_CLIENT_SSL;
    }
    $conn->real_connect(DB_HOST, DB_USER, DB_PASS, null, DB_PORT, null, $flags);
} catch (mysqli_sql_exception $ex) {
    $hint = DB_HOST === 'localhost' || DB_HOST === '127.0.0.1'
        ? 'Open the XAMPP Control Panel and click <b>Start</b> next to MySQL, then reload this page.'   // most common cause on XAMPP
        : 'Check DB_HOST, DB_PORT, DB_USER, DB_PASS and DB_SSL in your environment variables.';
    die('<h2>Cannot connect to MySQL</h2><p>' . $hint . '</p>'
        . '<p><small>' . htmlspecialchars($ex->getMessage()) . '</small></p>');
}

$conn->set_charset('utf8mb4');

// Use the app's time zone for DEFAULT CURRENT_TIMESTAMP columns
// (hosted databases run on UTC; XAMPP uses the PC's clock)
$conn->query("SET time_zone = '" . date('P') . "'");

// 2) Create the database if it doesn't exist yet, then select it.
// Hosted databases are usually created in their dashboard, and the
// account may not be allowed to create one, so a failure here is fine.
try {
    $conn->query('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
} catch (mysqli_sql_exception $ex) {
}
$conn->select_db(DB_NAME);

// 3) First run? (no `users` table yet) -> build tables and add sample data.
// Older installs that miss the newer tables just get those created.
$tables = array_column($conn->query('SHOW TABLES')->fetch_all(), 0);
if (!in_array('users', $tables, true)) {
    install_database();
} elseif (!in_array('sessions', $tables, true) || !in_array('images', $tables, true)) {
    create_tables();
}

// CA certificate for encrypted connections: DB_SSL_CA, or the server's usual bundle
function db_ca_file(): ?string
{
    if (DB_SSL_CA !== '') {
        return DB_SSL_CA;
    }
    foreach (['/etc/pki/tls/certs/ca-bundle.crt', '/etc/ssl/certs/ca-certificates.crt', '/etc/ssl/cert.pem'] as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
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
    create_tables();

    // Sample categories, products, pictures and a demo account
    require_once __DIR__ . '/seed.php';
    seed_sample_data();
}

// Runs database.sql (every statement is CREATE TABLE IF NOT EXISTS, so it's safe to repeat)
function create_tables(): void
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
}
