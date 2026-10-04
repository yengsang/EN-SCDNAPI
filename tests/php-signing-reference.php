<?php
// Generate golden fixtures with the upstream PHP Signer, not the JS adapter.
require $argv[1].'/src/Request/Request.php';
require $argv[1].'/src/Request/Signer.php';
$out = [];
foreach (['GET', 'POST'] as $method) {
    $defaults = ['user_id'=>0,'client_ip'=>'','client_userAgent'=>'vector-agent','fromadmin'=>0];
    $query = ['page'=>2,'data'=>['name'=>'hello world','domain'=>'example.com']];
    $body = ['name'=>'规则/one','rules'=>[['logic'=>'contains','data'=>['/login']]],'enabled'=>true];
    $url = 'https://api.example.com/V4/firewall.policy.save?'.http_build_query($method==='GET'?array_merge($defaults,$query):$query);
    $payload = json_encode($method==='GET'?[]:array_merge($defaults,$body));
    $request = new \edgenextapisdk\Request\Request($method,$url,['X-Sdk-Date'=>'20261004T070000Z'],$payload);
    $signer = new \edgenextapisdk\Request\Signer();
    $signer->Key = 'test-app';$signer->Secret='test-secret';
    $canonical=$signer->CanonicalRequest($request,[]);
    $signed=$signer->Sign($request);
    $out[]=['method'=>$method,'canonical'=>$canonical,'url'=>$signed->rawUrl,'body'=>$signed->body,'signature'=>$signed->headers['X-Auth-Sign']];
}
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
