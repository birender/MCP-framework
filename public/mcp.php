<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$acceptTypes = array_map('trim',explode(',', strtolower($accept)));

$acceptsJson = in_array('application/json', $acceptTypes, true);
$acceptsSse = in_array('text/event-stream', $acceptTypes, true);

if (!$acceptsJson || !$acceptsSse) {
    http_response_code(406);
    echo json_encode([
        'error' => 'Accept header must include application/json and text/event-stream'
    ]);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/McpServer.php';

// Allow only POST requests for this initial local test.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Parse the JSON-RPC request.
$raw = file_get_contents('php://input');
// Limit MCP request bodies to 1 MB.
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 1024 * 1024) {
    http_response_code(413);
    echo json_encode([
        'error' => 'Request body too large'
    ]);
    exit;
}
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

if ($mediaType !== 'application/json') {
    http_response_code(415);
    echo json_encode(['error' => 'Content-Type must be application/json']);
    exit;
}

$request = json_decode($raw, true);

// Malformed JSON is an HTTP-level client error.
if (json_last_error() !== JSON_ERROR_NONE) 
{
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

// Validate the JSON-RPC request structure.
if (!is_array($request) ||($request['jsonrpc'] ?? null) !== '2.0' ||!isset($request['method']) ||!is_string($request['method'])) {
    http_response_code(400);
    echo json_encode(['jsonrpc' => '2.0','id' => is_array($request) ? ($request['id'] ?? null) : null,'error' => ['code' => -32600,'message' => 'Invalid JSON-RPC request']]);
    exit;
}

try {
    $server = new McpServer(getPdo());    
    $response = $server->handle($request);
    // Notifications have no response body.
    if ($response === []) {
        http_response_code(202);
        exit;
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
} catch (Throwable $e) {
    error_log('MCP endpoint failure: ' . $e->getMessage());
    //echo "<pre>";print_r($e);die;
    http_response_code(500);
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $request['id'] ?? null,
        'error' => [
            'code' => -32603,
            'message' => 'Internal server error'
        ]
    ]);
}
