<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';

function api_result(int $status, array $data): array { return [$status, $data]; }
function api_error(int $status, string $error): array { return api_result($status, ['error'=>$error]); }
function handle_api(string $path, string $method, array $headers, string $raw, ?\edgenextapisdk\HttpClients\HttpClientInterface $transport = null): array {
    $base=getenv('SDK_API_PRE') ?: '';
    $appId=getenv('SDK_APP_ID') ?: '';
    $secret=getenv('SDK_APP_SECRET') ?: '';
    if ($path==='/api/connection') {
        if ($method!=='GET') return api_error(405,'Only GET is supported.');
        return api_result(200,['configured'=>(bool)($base&&$appId&&$secret),'mode'=>'php-sdk','baseUrl'=>$base?:null]);
    }
    if ($path!=='/api/request') return api_error(404,'Unknown API route.');
    if ($method!=='POST') return api_error(405,'Only POST is supported.');
    $configuredOrigins=getenv('APP_ORIGINS') ?: (getenv('APP_ORIGIN') ?: 'http://localhost:8080');
    $origins=array_values(array_filter(array_map(static fn(string $origin): string => rtrim(trim($origin),'/'),explode(',',$configuredOrigins)),static fn(string $origin): bool => $origin!==''));
    if (!in_array($headers['origin'] ?? '',$origins,true) || ($headers['sec-fetch-site'] ?? '')==='cross-site') return api_error(403,'Requests must come from an origin listed in APP_ORIGIN or APP_ORIGINS.');
    if (!str_starts_with(strtolower($headers['content-type'] ?? ''),'application/json')) return api_error(415,'Use application/json.');
    if (strlen($raw)>131072) return api_error(413,'Request exceeds 128 KB.');
    try { $input=json_decode($raw,false,512,JSON_THROW_ON_ERROR); } catch (Throwable $e) { return api_error(400,'Invalid JSON request.'); }
    if (!is_object($input)||!isset($input->method,$input->path,$input->query,$input->body)
        ||!in_array($input->method,['GET','POST','PUT','PATCH','DELETE'],true)
        ||!is_string($input->path)||!preg_match('/^[A-Za-z0-9_.\/-]+$/D',$input->path)
        ||str_starts_with($input->path,'//')||str_contains($input->path,'..')
        ||!is_object($input->query)||!is_object($input->body)) return api_error(400,'Invalid request envelope.');
    if (!$base||!$appId||!$secret) return api_error(503,'Configure SDK_API_PRE, SDK_APP_ID, and SDK_APP_SECRET on the server.');
    $parts=parse_url($base);
    if (!$parts||($parts['scheme']??'')!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])) return api_error(503,'Configure a valid HTTPS API base URL.');
    $started=microtime(true);
    try {
        $sdk=new \edgenextapisdk\Sdk([
            'app_id'=>$appId,'app_secret'=>$secret,'base_api_url'=>rtrim($base,'/').'/',
            'client_userAgent'=>'EdgeNext API Explorer / PHP SDK',
            'log'=>false,'throwException'=>true,'handler'=>$transport ?? new \Explorer\HttpClient(),
        ]);
        $params=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        $target=str_starts_with($params['path'],'/')?'https://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'').$params['path']:$params['path'];
        $call=strtolower($params['method']);
        $response=$sdk->$call(['url'=>$target,'query'=>$params['query'],'body'=>$params['body'],'timeout'=>15]);
        if (strlen($response)>2000000) return api_error(502,'Response exceeds 2 MB.');
        $full=$sdk->getSyncFullResponse();
        $data=json_decode($response,true);
        if (json_last_error()!==JSON_ERROR_NONE) $data=$response;
        return api_result(200,['status'=>$full['httpCode']??200,'duration'=>(int)((microtime(true)-$started)*1000),'data'=>$data]);
    } catch (Throwable $e) { return api_error(502,'The EdgeNext SDK request failed. Check the server connection settings and API availability.'); }
}
