<?php
namespace edgenextapisdk\Http;

use edgenextapisdk\Exceptions\SdkException;

class RawRequest
{
    protected $url;
    protected $headers;
    protected $body;
    protected $method;
    protected $base_api_url;
    protected $timeout;
    protected $urlParams;
    protected $body_type;
    protected $options;

    public function __construct($method = '', $url = '', array $headers = [], $body = null, $timeout = 10, $urlParams = [])
    {
        $this->method    = strtoupper($method);
        $this->url       = $url;
        $this->headers   = $headers;
        $this->body      = $body;
        $this->timeout   = (int) $timeout;
        $this->urlParams = $urlParams;
    }

    /**
     * @param $headers
     * @node_name 设置请求头
     *
     * @see
     * @desc
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * @param $k
     * @param $v
     * @node_name 单个头设置
     *
     * @see
     * @desc
     */
    public function setHeader($k, $v)
    {
        $this->headers[$k] = $v;
    }

    /**
     * @return array
     * @node_name 获取请求头
     *
     * @see
     * @desc
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param $base_api_url
     * @node_name 设置基url
     *
     * @see
     * @desc
     */
    public function setBaseApiUrl($base_api_url)
    {
        if ('/' != substr($this->base_api_url, -1)) {
            $this->base_api_url .= '/';
        }
        $this->base_api_url = $base_api_url;
    }

    /**
     * @param $url
     *
     * @throws SdkException
     * @node_name 设置请求url
     *
     * @see
     * @desc
     */
    public function setUrl($url)
    {
        //http https开头的url
        if (false !== stripos($url, 'http://') || false !== stripos($url, 'https://')) {
            $this->url = $url;
        } else {
            if (empty($this->base_api_url)) {
                throw new SdkException('must set base api url first');
            }
            $api_url   = $this->base_api_url.$url;
            $this->url = $api_url;
        }
    }

    /**
     * @return mixed
     * @node_name 获取请求url
     *
     * @see
     * @desc
     */
    public function getUrl()
    {
        $urlParams = $this->getUrlParams();
        if (is_array($urlParams) && count($urlParams) > 0) {
            $this->url .= (false === strpos($this->url, '?')) ? '?' : '&';
            $this->url .= self::build_query($urlParams);
        }

        return $this->url;
    }

    /**
     * @param $timeOut
     * @node_name 设置超时时间
     *
     * @see
     * @desc
     */
    public function setTimeOut($timeOut)
    {
        $this->timeout = $timeOut;
    }

    /**
     * @return int
     * @node_name 获取超时时间
     *
     * @see
     * @desc
     */
    public function getTimeOut()
    {
        return $this->timeout;
    }

    /**
     * @param $urlParams
     * @node_name 设置url params用于附加在url后面
     *
     * @see
     * @desc
     */
    public function setUrlParams($urlParams)
    {
        $this->urlParams = $urlParams;
    }

    /**
     * @return mixed
     * @node_name 获取url params
     *
     * @see
     * @desc
     */
    public function getUrlParams()
    {
        return $this->urlParams;
    }

    /**
     * @param $params
     *
     * @return string
     * @node_name x-www-form-urlencoded
     *
     * @see
     * @desc
     */
    public static function build_query($params)
    {
        if (function_exists('http_build_query')) {
            return http_build_query($params, '', '&');
        } else {
            foreach ($params as $name => $value) {
                $elements[] = "{$name}=".urlencode($value);
            }

            return implode('&', $elements);
        }
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    /**
     * @return null
     * @node_name 获取body
     *
     * @see
     * @desc
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param $method
     * @node_name 设置请求方法
     *
     * @see
     * @desc
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * @return string
     * @node_name 获取请求方法
     *
     * @see
     * @desc
     */
    public function getMethod()
    {
        return $this->method;
    }

    public function setBodyType($type)
    {
        $this->body_type = strtolower($type);
    }

    public function getBodyType()
    {
        return $this->body_type;
    }

    public function setOptions($options)
    {
        $this->options = $options;
    }

    public function getOptions()
    {
        return $this->options;
    }
}
