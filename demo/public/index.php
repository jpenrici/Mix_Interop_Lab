<?php

declare(strict_types=1);

use Demo\MatrixEngine;

require __DIR__ . '/../src/MatrixEngine.php';

/** @param array<string, mixed> $payload */
function respond(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(int $status, string $message): never
{
    respond($status, ['status' => 'error', 'error' => $message]);
}

$action = $_GET['action'] ?? '';

if ($action === '') {
    respond(200, ['message' => 'Mix PHP server running. POST two matrices to ?action=multiply.']);
}

if ($action !== 'multiply') {
    fail(404, 'Unknown action.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    fail(405, 'Use POST.');
}

if (!str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    fail(415, 'Content-Type must be application/json.');
}

try {
    $body = json_decode(
        (string) file_get_contents('php://input'),
        associative: true,
        depth: 8,
        flags: JSON_THROW_ON_ERROR
    );
} catch (JsonException) {
    fail(400, 'Request body is not valid JSON.');
}

if (!is_array($body) || !array_key_exists('a', $body) || !array_key_exists('b', $body)) {
    fail(400, 'Body must be an object with matrices "a" and "b".');
}

try {
    $engine = new MatrixEngine(MatrixEngine::libraryPath(__DIR__ . '/lib'));
    $result = $engine->multiply($body['a'], $body['b']);
} catch (InvalidArgumentException $e) {
    fail(400, $e->getMessage());
} catch (DomainException $e) {
    fail(422, $e->getMessage());
} catch (Throwable $e) {
    error_log((string) $e);
    fail(500, 'The computation engine is unavailable.');
}

respond(200, [
    'status'     => 'success',
    'dimensions' => ['rows' => count($result), 'cols' => count($result[0])],
    'result'     => $result,
]);
