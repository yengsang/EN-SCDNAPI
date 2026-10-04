<?php
namespace edgenextapisdk\Request;

define("BasicDateFormat", "Ymd\THis\Z");
define("Algorithm", "SDK-HMAC-SHA256");
define("HeaderXDate", "X-Sdk-Date");
define("HeaderHost", "host");
define("HeaderAuthorization", "X-Auth-Sign");
define("HeaderContentSha256", "X-Sdk-Content-Sha256");

class Request
{
    public $method = '';
    public $scheme = '';
    public $host = '';
    public $uri = '';
    public $query = array();
    public $headers = array();
    public $body = '';
    public $rawUrl = '';

    function __construct()
    {
        $args = func_get_args();
        $i = count($args);
        if ($i == 0) {
            $this->construct(NULL, NULL, NULL, NULL);
        } elseif ($i == 1) {
            $this->construct($args[0], NULL, NULL, NULL);
        } elseif ($i == 2) {
            $this->construct($args[0], $args[1], NULL, NULL);
        } elseif ($i == 3) {
            $this->construct($args[0], $args[1], $args[2], NULL);
        } else {
            $this->construct($args[0], $args[1], $args[2], $args[3]);
        }
    }

    function construct($method, $url, $headers, $body)
    {
        if ($method != NULL) {
            $this->method = $method;
        }
        if ($url != NULL) {
            $spl = explode("://", $url, 2);
            $scheme = 'http';
            if (count($spl) > 1) {
                $scheme = $spl[0];
                $url = $spl[1];
            }
            $spl = explode("?", $url, 2);
            $url = $spl[0];
            $query = array();
            if (count($spl) > 1) {
                foreach (explode("&", $spl[1]) as $kv) {
                    $spl = explode("=", $kv, 2);
                    $key = $spl[0];
                    if (count($spl) == 1) {
                        $value = "";
                    } else {
                        $value = $spl[1];
                    }
                    if ($key != "") {
                        $key = urldecode($key);
                        $value = urldecode($value);
                        if (array_key_exists($key, $query)) {
                            array_push($query[$key], $value);
                        } else {
                            $query[$key] = array($value);
                        }
                    }
                }
            }
            $spl = explode("/", $url, 2);
            $host = $spl[0];
            if (count($spl) == 1) {
                $url = "/";
            } else {
                $url = "/" . $spl[1];
            }
            $this->scheme = $scheme;
            $this->host = $host;
            $this->uri = urldecode($url);
            $this->query = $query;
        }
        if ($headers != NULL) {
            $this->headers = $headers;
        }
        if ($body != NULL) {
            $this->body = $body;
        }
    }
}

