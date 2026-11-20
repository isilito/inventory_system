<?php
/**
 * chat.php — the chatbot's back end (called by assets/chat.js, never opened directly)
 *
 * 1. The browser POSTs the conversation as JSON: {"messages": [{role, content}, ...]}
 * 2. We check it, add the system prompt + live inventory, and hand it to
 *    ai/gemini_chat.py (Python), which sends it to Google Gemini
 * 3. Gemini's answer is "streamed": Python prints it a few words at a time, and we pass
 *    each piece straight on to the browser so the reply appears as it's written.
 *
 * The browser receives one JSON object per line ("NDJSON"):
 *   {"type":"text","text":"Hello"}      a piece of the answer
 *   {"type":"done","stop":"done"}       finished (stop: done / max_tokens / blocked)
 *   {"type":"error","message":"..."}    something went wrong
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/chat.php';

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

// ---------- 3. Run the Python script and stream its answer back ----------

// Turn off every output buffer so each piece reaches the browser immediately
@ini_set('zlib.output_compression', '0');
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
while (ob_get_level() > 0) {
    ob_end_flush();
}
set_time_limit(150);

header('Content-Type: application/x-ndjson; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

// proc_open() starts a program and gives us "pipes" to talk to it:
//   0 = its input (we write the question), 1 = its output (we read the answer).
// Its error output (2) goes to a temp file, so we can show it if Python crashes.
$errorLog = tempnam(sys_get_temp_dir(), 'pbchat');
$process  = proc_open(
    [PYTHON_BIN, __DIR__ . '/ai/gemini_chat.py'],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errorLog, 'w']],
    $pipes,
    __DIR__
);
if (!is_resource($process)) {
    fail(500, 'Could not start Python. Check PYTHON_BIN in config.php.');
}

// Send the question to Python, then close its input so it knows we're done
fwrite($pipes[0], json_encode([
    'system'   => chat_system_prompt($user, chat_inventory_snapshot()),
    'messages' => $messages,
], JSON_UNESCAPED_UNICODE));
fclose($pipes[0]);

// Python prints one JSON line per piece of the answer. Pass each one on
// to the browser as soon as it arrives.
$finished = false;
while (($line = fgets($pipes[1])) !== false) {
    $event = json_decode($line, true);
    if (!is_array($event)) {
        continue;                       // ignore anything that isn't our JSON
    }
    send_event($event);
    if (in_array($event['type'] ?? '', ['done', 'error'], true)) {
        $finished = true;
    }
}
fclose($pipes[1]);
$exitCode = proc_close($process);

// Python stopped without saying "done" or "error": it crashed
if (!$finished) {
    $details = trim((string) file_get_contents($errorLog));
    $lines    = explode("\n", $details);
    $lastLine = $details === '' ? "exit code $exitCode" : end($lines);   // e.g. ModuleNotFoundError: ...
    send_event(['type' => 'error', 'message' => 'The Python script failed: ' . $lastLine]);
}
@unlink($errorLog);
