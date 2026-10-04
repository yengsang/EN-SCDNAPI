<?php
namespace edgenextapisdk\HttpClients;

use GuzzleHttp\Client;
use edgenextapisdk\Http\RawResponse;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use edgenextapisdk\Exceptions\ExceptionCodeMsg;
use edgenextapisdk\Exceptions\HttpClientException;

class GuzzleHttpClient implements HttpClientInterface
{
    /**
     * @var \GuzzleHttp\Client The Guzzle client.
     */
    protected $guzzleClient;

    /**
     * @param \GuzzleHttp\Client|null The Guzzle client.
     * @param null|Client $guzzleClient
     */
    public function __construct(Client $guzzleClient = null)
    {
        $this->guzzleClient = $guzzleClient ?: new Client();
    }

    /**
     * {@inheritdoc}
     */
    public function send($url, $method, $body, array $headers, $timeOut, $otherOptions = [])
    {
        if ($body && !is_string($body)) {
            throw new HttpClientException('guzzle body must be string');
        }
        $options = [
            'headers'         => $headers,
            'timeout'         => $timeOut,
            'connect_timeout' => 10,
            'body'            => $body,
        ];

//        if (isset($headers['Content-Type']) && 'application/json' == $headers['Content-Type']) {
//            $options['json'] = json_decode($body, true);
//        } elseif (isset($headers['Content-Type']) && 'application/x-www-form-urlencoded' == $headers['Content-Type']) {
//            parse_str($body, $content);
//            $options['form_params'] = $content;
//        } else {
//            throw new HttpClientException(ExceptionCodeMsg::MSG_GUZZLEHTTPCLIENT_SEND_1, ExceptionCodeMsg::CODE_GUZZLEHTTPCLIENT_SEND_1);
//        }

        if (isset($otherOptions['async']) && $otherOptions['async']) {
            $callback = $otherOptions['callback'] ?: function () {};
            $exception = $otherOptions['exception'] ?: function () {};
            $promise = $this->guzzleClient->requestAsync($method, $url, $options);
            $promise->then(
                $callback,
                $exception
            )->wait();
        } else {
            try {
                $rawResponse = $this->guzzleClient->request($method, $url, $options);
            } catch (RequestException $e) {
                $rawResponse = $e->getResponse();
                if (!$rawResponse instanceof ResponseInterface) {
                    throw new HttpClientException($e->getMessage(), $e->getCode());
                }
            }
            $rawHeaders     = $this->getHeadersAsString($rawResponse);
            $rawBody        = $rawResponse->getBody()->getContents();
            $httpStatusCode = $rawResponse->getStatusCode();

            return new RawResponse($rawHeaders, $rawBody, $httpStatusCode);
        }
    }

    /**
     * Returns the Guzzle array of headers as a string.
     *
     * @param ResponseInterface $response The Guzzle response.
     *
     * @return string
     */
    public function getHeadersAsString(ResponseInterface $response)
    {
        $headers    = $response->getHeaders();
        $rawHeaders = [];
        foreach ($headers as $name => $values) {
            $rawHeaders[] = $name.': '.implode(', ', $values);
        }

        return implode("\r\n", $rawHeaders);
    }
}
