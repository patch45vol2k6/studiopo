<?php
/**
 * writer.php — single POST endpoint for the Scriptwise writer page.
 *
 * Only accepts POST. Two actions, sent as a POST field named "action":
 *   action=save  -> saves title + content (JSON body: {title, content})
 *   action=load  -> returns the last saved title + content
 *
 * Storage: a single JSON file on disk (data/document.json). No database,
 * no accounts — this keeps one shared document, which matches "a place
 * to write like Google Docs" for a single-user/local demo. For many
 * users or many documents, this would need real auth and one file (or
 * DB row) per document instead.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Only POST is allowed, as requested.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'Only POST is allowed.']);
    exit;
}

$dataDir  = __DIR__ . '/data';
$dataFile = $dataDir . '/document.json';

// Make sure the storage folder exists and is writable.
if (!is_dir($dataDir)) {
    if (!mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not create storage folder.']);
        exit;
    }
}

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $title   = isset($_POST['title']) ? (string) $_POST['title'] : '';
    $content = isset($_POST['content']) ? (string) $_POST['content'] : '';

    // Basic limits so one request can't write an unbounded file.
    if (mb_strlen($title) > 200) {
        $title = mb_substr($title, 0, 200);
    }
    if (mb_strlen($content) > 2_000_000) { // ~2MB of text/HTML
        http_response_code(413);
        echo json_encode(['ok' => false, 'error' => 'Document is too large to save.']);
        exit;
    }

    $record = [
        'title'    => $title,
        'content'  => $content, // stored as the editor's HTML; the page escapes/sanitizes on display
        'savedAt'  => date(DATE_ATOM),
    ];

    $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not encode document.']);
        exit;
    }

    $fp = fopen($dataFile, 'c+');
    if ($fp === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not open storage file.']);
        exit;
    }

    $saved = false;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        $saved = fwrite($fp, $json) !== false;
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    if (!$saved) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not write to storage file.']);
        exit;
    }

    echo json_encode(['ok' => true, 'savedAt' => $record['savedAt']]);
    exit;
}

if ($action === 'load') {
    if (!is_file($dataFile)) {
        // Nothing saved yet — that's a normal first run, not an error.
        echo json_encode(['ok' => true, 'title' => '', 'content' => '', 'savedAt' => null]);
        exit;
    }

    $raw = file_get_contents($dataFile);
    $record = $raw !== false ? json_decode($raw, true) : null;

    if (!is_array($record)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Saved document is unreadable.']);
        exit;
    }

    echo json_encode([
        'ok'      => true,
        'title'   => $record['title'] ?? '',
        'content' => $record['content'] ?? '',
        'savedAt' => $record['savedAt'] ?? null,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown or missing action. Use "save" or "load".']);
