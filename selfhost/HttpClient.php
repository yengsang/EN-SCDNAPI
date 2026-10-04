<?php
declare(strict_types=1);
namespace Explorer;
use edgenextapisdk\HttpClients\HttpClientInterface;
use edgenextapisdk\Http\RawResponse;
use edgenextapisdk\Exceptions\HttpClientException;

// Use the SDK's supported transport interface; signing stays in the SDK.
final class HttpClient implements HttpClientInterface {
    public function send($url, $method, $body, array $headers, $timeOut, $options = []) {
        if (!extension_loaded('curl')) throw new HttpClientException('PHP cURL is required.');
        $headers['Content-Type'] = 'application/json';
        $lines = [];
        foreach ($headers as $name => $value) $lines[] = $name.': '.$value;
        $curl = curl_init();
        $response = '';
        $responseHeaders = [];
        $tooLarge = false;
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => min(15, (int)$timeOut),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function($handle, string $line) use (&$responseHeaders): int {
                if (str_contains($line, ':')) { [$key,$value] = explode(':',$line,2); $responseHeaders[trim($key)] = trim($value); }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function($handle, string $chunk) use (&$response,&$tooLarge): int {
                if (strlen($response)+strlen($chunk)>2000000) { $tooLarge=true; return 0; }
                $response .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($tooLarge) throw new HttpClientException('Response exceeds 2 MB.');
        if ($ok === false) throw new HttpClientException('The upstream request failed.');
        return new RawResponse($responseHeaders, $response, $status);
    }
}
