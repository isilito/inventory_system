<?php
/**
 * session.php
 * Stores PHP sessions in the `sessions` table instead of files.
 *
 * On XAMPP files would work, but on Vercel every request may run on a
 * different server with its own empty disk, so a session saved as a file
 * would be gone on the next click and the user would be logged out.
 * PHP calls these methods itself; we only register the handler.
 */

class DbSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        return true;   // the database connection is already open
    }

    public function close(): bool
    {
        return true;
    }

    // Returns the saved session data ('' for a new session)
    public function read(string $id): string|false
    {
        $row = db_row('SELECT data FROM sessions WHERE id = ?', 's', [$id]);
        return $row['data'] ?? '';
    }

    // Saves the session data (REPLACE = insert, or overwrite if the id exists)
    public function write(string $id, string $data): bool
    {
        db_run('REPLACE INTO sessions (id, data, updated_at) VALUES (?, ?, ?)', 'ssi', [$id, $data, time()]);
        return true;
    }

    // Logout / session_regenerate_id(true)
    public function destroy(string $id): bool
    {
        db_run('DELETE FROM sessions WHERE id = ?', 's', [$id]);
        return true;
    }

    // Now and then PHP removes sessions nobody used for $maxLifetime seconds
    public function gc(int $maxLifetime): int|false
    {
        return db_run('DELETE FROM sessions WHERE updated_at < ?', 'i', [time() - $maxLifetime])->affected_rows;
    }
}

session_set_save_handler(new DbSessionHandler(), true);
