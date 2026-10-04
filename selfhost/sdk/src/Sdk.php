<?php

namespace edgenextapisdk;

/*
 * 1. resetful
 * 2. 请求体支持array[application/x-www-form-urlencoded],json['application/json]
 * 3. 支持异步请求
 */

use edgenextapisdk\Http\HttpLib;
use edgenextapisdk\Request\Request;
use edgenextapisdk\Request\Signer;
use InvalidArgumentException;
use edgenextapisdk\Http\HttpOutput;
use edgenextapisdk\Http\RawRequest;
use edgenextapisdk\Http\RawResponse;
use edgenextapisdk\SignRequest\SignedRequest;
use GuzzleHttp\Exception\RequestException;
use edgenextapisdk\Exceptions\ExceptionCodeMsg;
use edgenextapisdk\Exceptions\SdkException;
use edgenextapisdk\Exceptions\HttpClientException;
use edgenextapisdk\HttpClients\HttpClientsFactory;

class Sdk
{
    const SDK_VERSION = "2.0.0";
    private $app_id; //必需
    private $app_secret; //必需
    private $user_id; // 用户id, 仅代理需要
    private $client_ip; //客户端ip
    private $client_userAgent; //客户端userAgent
    private $base_api_url; //api base url
    private $host;
    private $http_client_handler; //http client handler
    private $request; //request 对象
    private $syncExceptionOutput  = [
        'code'    => 0,
        'message' => '同步请求异常！请稍后重试！或者联系技术支持！',
    ];
    private $asyncExceptionOutput = [
        'code'    => 0,
        'message' => '异步请求异常！请稍后重试！或者联系技术支持！',
    ];

    private $log    = true;
    private $logfileWin;
    private $logfileLinux;
    private $config = [];
    private $asyncResponse = [];
    private $syncResponse = [];

    public function __construct($param)
    {
        if (!is_array($param)) {
            throw new SdkException('param must be array');
        }
        $param['client_userAgent'] = isset($param['client_userAgent']) ? trim($param['client_userAgent']) : '';
        $defaultUserAgent = sprintf('Sdk %s; php/%s; %s %s', self::SDK_VERSION, phpversion(), php_uname('s'), php_uname('r'));
        $this->config           = $param;
        $this->app_id           = $param['app_id'];
        $this->app_secret       = $param['app_secret'];
        $this->user_id          = isset($param['user_id']) ? (int)$param['user_id'] : 0;
        $this->client_ip        = isset($param['client_ip']) ? trim($param['client_ip']) : '';
        $this->client_userAgent = $param['client_userAgent'] ? $param['client_userAgent'] : $defaultUserAgent;
        $this->base_api_url     = isset($param['base_api_url']) ? trim($param['base_api_url']) : '';
        $this->host             = isset($param['host']) && !empty($param['host']) ? $param['host'] : '';
        if (isset($param['syncExceptionOutputCode'])) {
            $this->syncExceptionOutput['code'] = $param['syncExceptionOutputCode'];
        }
        if (isset($param['syncExceptionOutputMessage'])) {
            $this->syncExceptionOutput['message'] = $param['syncExceptionOutputMessage'];
        }
        if (isset($param['asyncExceptionOutputCode'])) {
            $this->asyncExceptionOutput['code'] = $param['asyncExceptionOutputCode'];
        }
        if (isset($param['asyncExceptionOutputMessage'])) {
            $this->asyncExceptionOutput['message'] = $param['asyncExceptionOutputMessage'];
        }
        if (isset($param['logfileWin'])) {
            $this->logfileWin = $param['logfileWin'];
        }
        if (isset($param['logfileLinux'])) {
            $this->logfileLinux = $param['logfileLinux'];
        }
        if (isset($param['log'])) {
            $this->log = (bool)$param['log'];
        }
        $this->request = new RawRequest('', '', [], null, 10, []);
        $this->request->setBaseApiUrl($this->base_api_url);
        $handler                   = isset($param['handler']) ? $param['handler'] : '';
        $this->http_client_handler = HttpClientsFactory::createHttpClient($handler);
    }

    /**
     * @param RawRequest $request
     *
     * @return Http\RawResponse
     * @node_name 签名请求
     *
     * @see
     * @desc
     */
    public function signedRequest(RawRequest $request)
    {
        $body_string = "";
        if ('json' == $request->getBodyType() || 'array' == $request->getBodyType()) {
            $body_string = json_encode($request->getBody());
        }
        $this->request->setHeader("algorithm",isset($body['algorithm']) ? $body['algorithm'] : 'HMAC-SHA256');
        $this->request->setHeader("issued_at",isset($body['issued_at']) ? $body['issued_at'] : time());
        //签名
        $signer = new Signer();
        $signer->Key = $this->app_id;
        $signer->Secret = $this->app_secret;
        $req = new Request(
            $this->request->getMethod(),
            $this->request->getUrl(),
            $this->request->getHeaders(),
            $body_string
        );
        $signReq = $signer->sign($req);
        // 签名重写了url header
        $url     = $signReq->rawUrl;
        $headers = $signReq->headers;
        $method  = $signReq->method;
        $body = $signReq->body;
        $timeOut = $request->getTimeOut();
        $options = $request->getOptions();
        $this->log('请求参数-request对象-' . print_r($request, true));
        return $this->http_client_handler->send($url, $method, $body, $headers, $timeOut, $options);
    }

    /**
     * @param $request
     *
     * @throws SdkException
     *
     * @return RawRequest
     * @node_name build request
     *
     * @see
     * @desc
     */
    private function build_request($request)
    {
        $defaultRequest = [
            'url'     => '',
            'body'    => [],
            'method'  => 'GET',
            'headers' => [
                'format' => 'json',
            ],
            'timeout' => 10,
            'query'   => [],
            'options' => [],
        ];
        $request        = array_merge($defaultRequest, $request);
        $defaultData = [
            'user_id'          => $this->user_id,
            'client_ip'        => $this->client_ip,
            'client_userAgent' => $this->client_userAgent,
            'fromadmin'        => isset($_SESSION['fromadmin']) ? intval($_SESSION['fromadmin']) : 0,
        ];
        foreach ($request['headers'] as $h => $v) {
            $hs = strtolower($h);
            $vs = strtolower($v);
            if ('content-type' == $hs) {
                unset($request['headers'][$h]);
                $request['headers']['Content-Type'] = $vs;
            }
        }
        if (is_string($request['body'])) {
            $json_decode_content = HttpLib::isCorrectJson($request['body']);
            if (false === $json_decode_content) {
                throw new SdkException(ExceptionCodeMsg::MSG_SDK_BUILD_REQUEST_2, ExceptionCodeMsg::CODE_SDK_BUILD_REQUEST_2);
            }
            $request['body'] = array_merge($defaultData, $json_decode_content);
            $request['body'] = json_encode($request['body']);
            if ('GET' == strtoupper($request['method'])) {
                $request['body'] = '';
            }
            $this->request->setBodyType('json');
            $request['headers']['Content-Type'] = 'application/json';
        } elseif (is_array($request['body'])) {
            $request['body'] = array_merge($defaultData, $request['body']);
            if ('GET' == strtoupper($request['method'])) {
                $request['body'] = [];
            }
            $this->request->setBodyType('array');
            $request['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
        } else {
            throw new SdkException(ExceptionCodeMsg::MSG_SDK_BUILD_REQUEST_1, ExceptionCodeMsg::CODE_SDK_BUILD_REQUEST_1);
        }
        if ('GET' == strtoupper($request['method'])) {
            $request['query'] = array_merge($defaultData, $request['query']);
        }
        if (isset($request['options']['async']) && $request['options']['async']) {
            $request['options']['callback']  = isset($request['options']['callback']) ? $request['options']['callback'] : [$this, 'async_callback'];
            $request['options']['exception'] = isset($request['options']['exception']) ? $request['options']['exception'] : [$this, 'async_callback_exception'];
        }
        $this->request->setBody($request['body']);
        $this->request->setUrl($request['url']);
        $this->request->setMethod($request['method']);
        $this->request->setTimeOut($request['timeout']);
        $this->request->setHeaders($request['headers']);
        isset($_SERVER['HTTP_SOCKETLOG']) && $this->request->setHeader('Socketlog', $_SERVER['HTTP_SOCKETLOG']);
        isset($_SERVER['HTTP_USER_AGENT']) && $this->request->setHeader('User-Agent', $_SERVER['HTTP_USER_AGENT']);
        if($this->host){
            $this->request->setHeader('HOST', $this->host);
        }
        $this->request->setUrlParams($request['query']);
        $this->request->setOptions($request['options']);
        return $this->request;
    }

    /**
     * @param $request
     *
     * @throws SdkException
     *
     * @return string
     * @node_name
     *
     * @see
     * @desc
     */
    private function api_call($request)
    {
//        $request = array(
//            'url' => '',
//            'body' => []/json,
//            'method' => '',
//            'headers' => [],
//            'timeout' => 10,
//            'query' => [],
//            'options' => [
//                'async' => true, //异步请求
//                'callback' => function(){},
//                'exception' => function(){}
//            ]
//        );
        $httpRequest = $this->build_request($request);
        try {
            $rawResponse = $this->signedRequest($httpRequest);
            if (!isset($request['options']['async']) || !$request['options']['async']) {
                $body = $rawResponse->getBody();
                $this->log('sync response headers:' . print_r($rawResponse->getHeaders(), true));
                $this->log('sync response body:' . print_r($rawResponse->getBody(), true));
                $this->log('sync response http_status_code:' . print_r($rawResponse->getHttpResponseCode(), true));
                $this->syncResponse = [
                    'headers' => $rawResponse->getHeaders(),
                    'body' => $rawResponse->getBody(),
                    'httpCode' => $rawResponse->getHttpResponseCode(),
                ];
                return $body;
            }else if(isset($request['options']['async']) && $request['options']['async']){
                $body = $this->asyncResponse['body'];
                return $body;
            }
        } catch (HttpClientException $e) {
            $this->log('HttpClientException: ' . $e->getMessage());
            $this->syncExceptionOutput($request, $e);
        } catch (RequestException $e) {
            $this->log('RequestException: ' . $e->getMessage());
            $this->syncExceptionOutput($request, $e);
        } catch (InvalidArgumentException $e) {
            $this->log('InvalidArgumentException: ' . $e->getMessage());
            $this->syncExceptionOutput($request, $e);
        } catch (\Exception $e) {
            $this->log('syncExceptionOutput: ' . $e->getMessage());
            $this->syncExceptionOutput($request, $e);
        }
    }

    public function get($request)
    {
        $request['method'] = 'GET';

        return $this->api_call($request);
    }

    public function post($request)
    {
        $request['method'] = 'POST';

        return $this->api_call($request);
    }

    public function put($request)
    {
        $request['method'] = 'PUT';

        return $this->api_call($request);
    }

    public function patch($request)
    {
        $request['method'] = 'PATCH';

        return $this->api_call($request);
    }

    public function delete($request)
    {
        $request['method'] = 'DELETE';

        return $this->api_call($request);
    }

    public function getAsync($request)
    {
        $request['method']           = 'GET';
        $request['options']['async'] = true;

        return $this->api_call($request);
    }

    public function postAsync($request)
    {
        $request['method']           = 'POST';
        $request['options']['async'] = true;

        return $this->api_call($request);
    }

    public function putAsync($request)
    {
        $request['method']           = 'PUT';
        $request['options']['async'] = true;

        return $this->api_call($request);
    }

    public function patchAsync($request)
    {
        $request['method']           = 'PATCH';
        $request['options']['async'] = true;

        return $this->api_call($request);
    }

    public function deleteAsync($request)
    {
        $request['method']           = 'DELETE';
        $request['options']['async'] = true;

        return $this->api_call($request);
    }

    public function async_callback($response)
    {
        $rawHeaders     = $this->http_client_handler->getHeadersAsString($response);
        $rawBody        = $response->getBody()->getContents();
        $httpStatusCode = $response->getStatusCode();
        $rawResponse    = new RawResponse($rawHeaders, $rawBody, $httpStatusCode);
        $body           = $rawResponse->getBody();
        $this->log('async response headers:' . print_r($rawResponse->getHeaders(), true));
        $this->log('async response body:' . print_r($rawResponse->getBody(), true));
        $this->log('async response http_status_code:' . print_r($rawResponse->getHttpResponseCode(), true));
        $format = isset($this->request->getHeaders()['format']) ? $this->request->getHeaders()['format'] : 'json';
        HttpOutput::setType($format);
//        HttpOutput::output($body);
        $this->asyncResponse = [
            'headers' => $rawResponse->getHeaders(),
            'body' => $rawResponse->getBody(),
            'httpCode' => $rawResponse->getHttpResponseCode(),
        ];
    }

    public function async_callback_exception($e)
    {
        if (isset($this->config['throwException']) && $this->config['throwException']) {
            throw $e;
        }
        $message = $e->getMessage();
        $method  = $e->getRequest()->getMethod();
        $this->log(__FUNCTION__ . ' message:' . $message . ',method:' . $method);
        $this->asyncExceptionOutput();
    }

    /**
     * @param $value
     * @param string $logFile
     * @node_name
     *
     * @see
     * @desc
     */
    public function log($value, $logFile = '')
    {
        if ($this->log) {
            if (empty($logFile)) {
                if (HttpLib::isWin()) {
                    $file = $this->logfileWin;
                } else {
                    $file = $this->logfileLinux;
                }
                if (!empty($file) && file_exists(dirname($file))) {
                    $logFile = $file;
                }
            }
            HttpLib::logSdk($value, $logFile);
        }
    }


    public function getSyncFullResponse(){
        return $this->syncResponse;
    }

    public function getAsyncFullResponse(){
        return $this->asyncResponse;
    }

    /**
     * @param $request
     * @param  $e
     * @node_name 同步请求失败响应
     *
     * @see
     * @desc
     */
    private function syncExceptionOutput($request, $e)
    {
        if (isset($this->config['throwException']) && $this->config['throwException']) {
            throw $e;
        }
        $format = isset($request['headers']['format']) ? $request['headers']['format'] : 'json';
        HttpOutput::setType($format);
        $body = [
            'status' => [
                'code'    => $this->syncExceptionOutput['code'],
                'message' => $this->syncExceptionOutput['message'],
            ],
            'data'   => [],
        ];
        HttpOutput::output($body);
    }

    /**
     * @node_name 异步请求失败响应
     *
     * @see
     * @desc
     */
    private function asyncExceptionOutput()
    {
        $format = isset($this->request->getHeaders()['format']) ? $this->request->getHeaders()['format'] : 'json';
        HttpOutput::setType($format);
        $body = [
            'status' => [
                'code'    => $this->asyncExceptionOutput['code'],
                'message' => $this->asyncExceptionOutput['message'],
            ],
            'data'   => [],
        ];
        HttpOutput::output($body);
    }
}
