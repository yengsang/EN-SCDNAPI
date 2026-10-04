<?php
declare(strict_types=1);
// Only this directory should be exposed by the web server.
ini_set('display_errors', '0');
header('Content-Type: application/json');
header('Cache-Control: no-store');
function fail(int $status, string $message): never {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Only POST is supported.');
$bridgeToken = getenv('EDGENEXT_PHP_BRIDGE_TOKEN');
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!$bridgeToken || !hash_equals('Bearer '.$bridgeToken, $auth)) fail(401, 'Invalid bridge authentication.');
$raw = file_get_contents('php://input', false, null, 0, 131073);
if ($raw === false || strlen($raw) > 131072) fail(413, 'Request exceeds 128 KB.');
try { $input = json_decode($raw, false, 512, JSON_THROW_ON_ERROR); }
catch (Throwable $e) { fail(400, 'Invalid JSON request.'); }
if (!is_object($input) || !isset($input->method, $input->path, $input->query, $input->body)
    || !in_array($input->method, ['GET','POST','PUT','PATCH','DELETE'], true)
    || !is_string($input->path) || !preg_match('/^[A-Za-z0-9_.\/-]+$/D', $input->path)
    || str_contains($input->path, '..') || !is_object($input->query) || !is_object($input->body)) fail(400, 'Invalid request envelope.');
$base = getenv('SDK_API_PRE');
$appId = getenv('SDK_APP_ID');
$secret = getenv('SDK_APP_SECRET');
if (!$base || !$appId || !$secret) fail(503, 'EdgeNext server credentials are not configured.');
$parts = parse_url($base);
if (!$parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user'], $parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) fail(503, 'Configure a valid HTTPS API base URL.');
$autoload = dirname(__DIR__).'/vendor/autoload.php';
if (!is_file($autoload)) fail(503, 'Install the PHP SDK with Composer first.');
require $autoload;
$started = microtime(true);
try {
    $sdk = new \edgenextapisdk\Sdk([
        'app_id' => $appId,
        'app_secret' => $secret,
        'base_api_url' => rtrim($base, '/').'/',
        'log' => false,
        'throwException' => true,
    ]);
    $params = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $method = strtolower($params['method']);
    $response = $sdk->$method([
        'url' => $params['path'],
        'query' => $params['query'],
        'body' => $params['body'],
        'timeout' => 10,
        'options' => ['allow_redirects' => false],
    ]);
    $full = $sdk->getSyncFullResponse();
    if (strlen($response) > 2000000) fail(502, 'Response exceeds 2 MB.');
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) $data = $response;
    echo json_encode(['status' => $full['httpCode'] ?? 200, 'duration' => (int)((microtime(true)-$started)*1000), 'data' => $data], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    fail(502, 'The EdgeNext SDK request failed. Check your server credentials and API availability.');
}
