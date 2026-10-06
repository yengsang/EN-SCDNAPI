<?php
declare(strict_types=1);
require dirname(__DIR__).'/selfhost/api.php';

// With credentials absent, 503 proves a valid request passed the origin guard
// without making any real EdgeNext calls. Untrusted requests must return 403.
foreach (['SDK_API_PRE','SDK_APP_ID','SDK_APP_SECRET'] as $key) putenv($key.'=');
$payload='{"method":"GET","path":"/api/v5/domains","query":{},"body":{}}';
$cases=[
    ['single origin remains supported','https://first.example',null,'https://first.example','same-origin',503],
    ['first listed domain accepted','https://old.example','https://first.example,https://second.example','https://first.example','same-origin',503],
    ['second listed domain accepted','https://old.example','https://first.example,https://second.example','https://second.example','same-origin',503],
    ['configuration whitespace and trailing slash tolerated',null,' https://first.example/ , https://second.example/ , ','https://second.example','same-origin',503],
    ['origin list overrides single origin','https://old.example','https://first.example,https://second.example','https://old.example','same-origin',403],
    ['unlisted domain rejected',null,'https://first.example,https://second.example','https://third.example','same-origin',403],
    ['suffix attack rejected',null,'https://first.example','https://first.example.attacker.example','same-origin',403],
    ['scheme must match',null,'https://first.example','http://first.example','same-origin',403],
    ['missing origin rejected',null,'https://first.example',null,'same-origin',403],
    ['cross-site request rejected even for listed origin',null,'https://first.example','https://first.example','cross-site',403],
    ['empty origin list falls back to single origin','https://first.example','','https://first.example','same-origin',503],
    ['local development default remains supported',null,null,'http://localhost:8080','same-origin',503],
    ['wildcards do not authorize arbitrary domains',null,'*','https://third.example','same-origin',403],
    ['port must match',null,'https://first.example:8443','https://first.example','same-origin',403],
    ['configured port accepted',null,'https://first.example:8443','https://first.example:8443','same-origin',503],
];
foreach ($cases as [$name,$single,$multiple,$origin,$fetchSite,$expected]) {
    putenv($single===null?'APP_ORIGIN':'APP_ORIGIN='.$single);
    putenv($multiple===null?'APP_ORIGINS':'APP_ORIGINS='.$multiple);
    $headers=['content-type'=>'application/json','sec-fetch-site'=>$fetchSite];
    if ($origin!==null) $headers['origin']=$origin;
    [$actual]=handle_api('/api/request','POST',$headers,$payload);
    if ($actual!==$expected) {
        fwrite(STDERR,$name.': expected '.$expected.', received '.$actual."\n");
        exit(1);
    }
}
echo count($cases)." origin validation checks passed.\n";
