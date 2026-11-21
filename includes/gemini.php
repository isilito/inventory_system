<?php
/**
 * gemini.php
 * Talks to Google Gemini through its REST API with curl (no Python, no SDK).
 *
 * gemini_stream($system, $messages, $onText) sends the conversation and calls
 * $onText('some text') for every piece of the answer as soon as it arrives.
 *
 * Returns ['stop' => 'done' | 'max_tokens' | 'blocked']   when the answer finished
 *      or ['error' => 'message for the user']            when something went wrong
 */

function gemini_stream(string $system, array $messages, callable $onText): array
{
    if (GEMINI_API_KEY === '') {
        return ['error' => 'No API key. Put GEMINI_API_KEY in the .env file (on Vercel: Settings → Environment Variables).'];
    }

    // Gemini calls the assistant "model", and each message holds a list of "parts"
    $contents = [];
    foreach ($messages as $m) {
        $contents[] = [
            'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ];
    }

    $body = json_encode([
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents'          => $contents,
        'generationConfig'  => [
            'maxOutputTokens' => 8192,
            'thinkingConfig'  => ['thinkingLevel' => GEMINI_THINKING],
        ],
    ], JSON_UNESCAPED_UNICODE);

    // streamGenerateContent + alt=sse sends the answer in small chunks as it's written
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
         . rawurlencode(GEMINI_MODEL) . ':streamGenerateContent?alt=sse';

    // Free-tier models are sometimes busy (429 / 500 / 503). Try up to 3 times,
    // waiting a little longer each time, but only if no text was shown yet.
    for ($attempt = 1; ; $attempt++) {
        $r = gemini_request($url, $body, $onText);

        $busy = in_array($r['status'], [429, 500, 503], true);
        if (!$busy || $r['sent'] || $attempt === 3) {
            break;
        }
        sleep(2 ** ($attempt - 1));   // 1 s, then 2 s
    }

    return gemini_result($r);
}

// One HTTP request to Gemini. Reads the streamed answer line by line.
function gemini_request(string $url, string $body, callable $onText): array
{
    $r = ['status' => 0, 'sent' => false, 'stop' => null, 'error_body' => '', 'curl_error' => ''];
    $buffer = '';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: ' . GEMINI_API_KEY],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 50,   // give up after 50 seconds (Vercel stops the page at 60)

        // curl calls this every time a piece of the response arrives
        CURLOPT_WRITEFUNCTION  => function ($ch, string $chunk) use (&$r, &$buffer, $onText): int {
            // An error answer is one plain JSON object: keep it for the message
            if (curl_getinfo($ch, CURLINFO_RESPONSE_CODE) !== 200) {
                $r['error_body'] .= $chunk;
                return strlen($chunk);
            }

            // A normal answer is "Server-Sent Events": lines like  data: {...json...}
            // A chunk can end in the middle of a line, so keep the unfinished part.
            $buffer .= $chunk;
            while (($end = strpos($buffer, "\n")) !== false) {
                $line   = rtrim(substr($buffer, 0, $end), "\r");
                $buffer = substr($buffer, $end + 1);

                if (strncmp($line, 'data:', 5) === 0) {
                    $event = json_decode(substr($line, 5), true);
                    if (is_array($event)) {
                        gemini_read_event($event, $r, $onText);
                    }
                }
            }
            return strlen($chunk);   // tell curl we handled every byte
        },
    ]);

    curl_exec($ch);
    $r['status']     = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $r['curl_error'] = curl_error($ch);

    return $r;
}

// Handles one streamed piece of the answer
function gemini_read_event(array $event, array &$r, callable $onText): void
{
    // The question itself was blocked by Google's safety filters
    if (!empty($event['promptFeedback']['blockReason'])) {
        $r['stop'] = 'blocked';
        return;
    }

    $candidate = $event['candidates'][0] ?? null;
    if ($candidate === null) {
        return;
    }

    foreach ($candidate['content']['parts'] ?? [] as $part) {
        // Skip the model's private "thinking" parts; pass on the answer text
        if (($part['text'] ?? '') !== '' && empty($part['thought'])) {
            $onText($part['text']);
            $r['sent'] = true;
        }
    }

    // finishReason appears on the last chunk: STOP = finished normally
    $reason = $candidate['finishReason'] ?? '';
    if ($reason === 'STOP') {
        $r['stop'] = 'done';
    } elseif ($reason === 'MAX_TOKENS') {
        $r['stop'] = 'max_tokens';
    } elseif ($reason !== '') {
        $r['stop'] = 'blocked';   // SAFETY, PROHIBITED_CONTENT, RECITATION, ...
    }
}

// Turns the raw request result into ['stop' => ...] or ['error' => ...]
function gemini_result(array $r): array
{
    $status = $r['status'];

    if ($r['curl_error'] !== '' && $r['stop'] === null) {   // no internet, timeout, ...
        return ['error' => 'Could not reach Gemini: ' . $r['curl_error']];
    }

    if ($status !== 200) {
        $message = json_decode($r['error_body'], true)['error']['message'] ?? trim($r['error_body']);

        if ($status === 429) {
            return ['error' => 'Free-tier limit reached. Wait a minute and try again.'];
        }
        if (in_array($status, [400, 401, 403], true) && stripos($message, 'key') !== false) {
            return ['error' => 'Gemini rejected the API key. Check GEMINI_API_KEY.'];
        }
        if ($status === 404) {
            return ['error' => "Model '" . GEMINI_MODEL . "' was not found. Change GEMINI_MODEL."];
        }
        if ($status >= 500) {
            return ['error' => "Gemini is having trouble ($status). Try again shortly."];
        }
        return ['error' => "Gemini returned an error ($status): $message"];
    }

    if ($r['stop'] === null) {
        return ['error' => 'The answer was cut off. Please try again.'];
    }
    return ['stop' => $r['stop']];
}
