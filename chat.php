<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/chat.php';
require_once __DIR__ . '/includes/gemini.php';

// Send one line of NDJSON to the browser right away
function send_event(array $event): void
{
    echo json_encode($event, JSON_UNESCAPED_UNICODE), "\n";
    flush();
}

// Send an error and stop
function fail(int $status, string $message): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/x-ndjson; charset=utf-8');
    }
    send_event(['type' => 'error', 'message' => $message]);
    exit;
}

// ---------- 1. Who is asking, and is the request genuine? ----------

if (!is_post()) {
    fail(405, 'Use POST.');
}
if (!is_logged_in()) {
    fail(401, 'Your session ended. Please log in again.');
}
// Our own script always sends this header. Another website can't add it
// to a request without the browser blocking it, so this stops other sites
// from using a logged-in browser to spend your API quota.
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'PartsBin') {
    fail(403, 'Request blocked.');
}

$user = current_user();

// We only needed the session to check the login. Closing it now lets the
// user keep opening other pages while the answer is still streaming
// (PHP locks the session file until the script ends otherwise).
session_write_close();

// ---------- 2. Read and check the conversation ----------

$input    = json_decode(file_get_contents('php://input'), true);
$messages = [];

foreach (($input['messages'] ?? []) as $m) {
    $role    = $m['role'] ?? '';
    $content = trim((string) ($m['content'] ?? ''));
    if (!in_array($role, ['user', 'assistant'], true) || $content === '') {
        continue;
    }
    $messages[] = ['role' => $role, 'content' => mb_substr($content, 0, CHAT_MAX_CHARS)];
}

// Keep the latest messages only, and make sure the list starts with the user
$messages = array_slice($messages, -CHAT_MAX_TURNS);
while ($messages && $messages[0]['role'] !== 'user') {
    array_shift($messages);
}
if (!$messages || end($messages)['role'] !== 'user') {
    fail(400, 'Type a question first.');
}

// ---------- 3. Ask Gemini and stream its answer back ----------

// Turn off every output buffer so each piece reaches the browser immediately
@ini_set('zlib.output_compression', '0');
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
while (ob_get_level() > 0) {
    ob_end_flush();
}
set_time_limit(90);

$system = chat_system_prompt($user, chat_inventory_snapshot());

header('Content-Type: application/x-ndjson; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

// Every piece of the answer goes to the browser as soon as Gemini sends it
$result = gemini_stream($system, $messages, function (string $text): void {
    send_event(['type' => 'text', 'text' => $text]);
});

if (isset($result['error'])) {
    send_event(['type' => 'error', 'message' => $result['error']]);
} else {
    send_event(['type' => 'done', 'stop' => $result['stop']]);
}
