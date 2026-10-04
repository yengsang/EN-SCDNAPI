<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH) ?: '/';
if ($path==='/healthz') { header('Content-Type: application/json'); echo '{"status":"ok"}'; exit; }
if (str_starts_with($path,'/api/')) {
    require dirname(__DIR__).'/api.php';
    header('Content-Type: application/json');header('Cache-Control: no-store');
    $headers=[];
    foreach (getallheaders() as $name=>$value) $headers[strtolower($name)]=$value;
    $raw=file_get_contents('php://input',false,null,0,131073) ?: '';
    [$status,$result]=handle_api($path,$_SERVER['REQUEST_METHOD']??'GET',$headers,$raw);
    http_response_code($status);
    echo json_encode($result,JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
$index=__DIR__.'/index.html';
if (!is_file($index)) { http_response_code(503); echo 'Build the frontend with npm run build, then copy dist/selfhost into selfhost/public.'; exit; }
readfile($index);
