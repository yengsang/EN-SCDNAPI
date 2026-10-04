<?php
namespace edgenextapisdk\Request;

class Signer
{
    public $Key = '';
    public $Secret = '';

    function escape($string)
    {
        $entities = array('+', "%7E");
        $replacements = array('%20', "~");
        return str_replace($entities, $replacements, urlencode($string));
    }

    function findHeader($r, $header)
    {
        foreach ($r->headers as $key => $value) {
            if (!strcasecmp($key, $header)) {
                return $value;
            }
        }
        return NULL;
    }

// Build a CanonicalRequest from a regular request string
//
// CanonicalRequest =
//  HTTPRequestMethod + '\n' +
//  CanonicalURI + '\n' +
//  CanonicalQueryString + '\n' +
//  HexEncode(Hash(RequestPayload))
    function CanonicalRequest($r, $signedHeaders)
    {
        $CanonicalURI = $this->CanonicalURI($r);
        $CanonicalQueryString = $this->CanonicalQueryString($r);
        //$canonicalHeaders = $this->CanonicalHeaders($r, $signedHeaders);
        //$signedHeadersString = join(";", $signedHeaders);
        $hash = $this->findHeader($r, HeaderContentSha256);
        if (!$hash) {
            $hash = hash("sha256", $r->body);
        }
        return "$r->method\n$CanonicalURI\n$CanonicalQueryString\n$hash";
    }

// CanonicalURI returns request uri
    function CanonicalURI($r)
    {
        $pattens = explode("/", $r->uri);
        $uri = array();
        foreach ($pattens as $v) {
            array_push($uri, $this->escape($v));
        }
        $urlpath = join("/", $uri);
        if (substr($urlpath, -1) != "/") {
            $urlpath = $urlpath . "/";
        }
        return $urlpath;
    }

// CanonicalQueryString
    function CanonicalQueryString($r)
    {
        $keys = array();
        foreach ($r->query as $key => $value) {
            array_push($keys, $key);
        }
        sort($keys);
        $a = array();
        foreach ($keys as $key) {
            $k = $this->escape($key);
            $value = $r->query[$key];
            if (is_array($value)) {
                sort($value);
                foreach ($value as $v) {
                    $kv = "$k=" . $this->escape($v);
                    array_push($a, $kv);
                }
            } else {
                $kv = "$k=" . $this->escape($value);
                array_push($a, $kv);
            }
        }
        return join("&", $a);
    }

// CanonicalHeaders
    function CanonicalHeaders($r, $signedHeaders)
    {
        $headers = array();
        foreach ($r->headers as $key => $value) {
            $headers[strtolower($key)] = trim($value);
        }
        $a = array();
        foreach ($signedHeaders as $key) {
            array_push($a, $key . ':' . $headers[$key]);
        }
        return join("\n", $a) . "\n";
    }

    function curlHeaders($r)
    {
        $header = array();
        foreach ($r->headers as $key => $value) {
            array_push($header, strtolower($key) . ':' . trim($value));
        }
        return $header;
    }

// SignedHeaders
    function SignedHeaders($r)
    {
        $a = array();
        foreach ($r->headers as $key => $value) {
            array_push($a, strtolower($key));
        }
        sort($a);
        return $a;
    }

// Create a "String to Sign".
    function StringToSign($canonicalRequest, $t)
    {
        date_default_timezone_set('UTC');
        $date = date(BasicDateFormat, $t);
        $hash = hash("sha256", $canonicalRequest);
        return "SDK-HMAC-SHA256\n$date\n$hash";
    }

// Create the HWS Signature.
    function SignStringToSign($stringToSign, $signingKey)
    {
        return hash_hmac("sha256", $stringToSign, $signingKey);
    }

// Get the finalized value for the "Authorization" header. The signature parameter is the output from SignStringToSign
    function AuthHeaderValue($signature, $accessKey, $signedHeaders)
    {
        $signedHeadersString = join(";", $signedHeaders);
        return "Bearer $signature";
    }

    public function Sign(Request $r)
    {
        date_default_timezone_set('UTC');
        $date = $this->findHeader($r, HeaderXDate);
        if ($date) {
            $t = date_timestamp_get(date_create_from_format(BasicDateFormat, $date));
        }
        if (!@$t) {
            $t = time();
            $r->headers[HeaderXDate] = date(BasicDateFormat, $t);
        }
        $queryString = $this->CanonicalQueryString($r);
        if ($queryString != "") {
            $queryString = "?" . $queryString;
        }
        $signedHeaders = $this->SignedHeaders($r);
        $canonicalRequest = $this->CanonicalRequest($r, $signedHeaders);
        $stringToSign = $this->StringToSign($canonicalRequest, $t);
        $signature = $this->SignStringToSign($stringToSign, $this->Secret);
        $uri = str_replace(array("%2F"), array("/"), rawurlencode($r->uri));
        $url = $r->scheme . '://' . $r->host . $uri . $queryString;
        $r->rawUrl = $url;
        $r->headers['X-Auth-App-Id'] = $this->Key;
        $r->headers['X-Auth-Sdk-Version'] = "2.0.0";
        $r->headers[HeaderAuthorization] = $this->AuthHeaderValue($signature, $this->Key, $signedHeaders);
        return $r;
    }
}
