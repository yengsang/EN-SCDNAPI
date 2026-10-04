<?php
// Development only; production uses Apache with selfhost/public as its web root.
declare(strict_types=1);
$root=realpath(__DIR__.'/public');
$path=urldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?: '/');
if (!str_contains($path,"\0")) {
    $file=realpath($root.'/'.$path);
    if ($file&&str_starts_with($file,$root.DIRECTORY_SEPARATOR)&&is_file($file)&&pathinfo($file,PATHINFO_EXTENSION)!=='php') return false;
}
require __DIR__.'/public/index.php';
