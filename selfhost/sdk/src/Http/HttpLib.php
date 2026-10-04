<?php
namespace edgenextapisdk\Http;

use edgenextapisdk\Exceptions\ExceptionCodeMsg;
use edgenextapisdk\Exceptions\SdkException;

class HttpLib
{
    /**
     * @param string $body
     *
     * @throws SdkException
     *
     * @return bool
     * @node_name 是否是合法的json
     *
     * @see
     * @desc
     */
    public static function isCorrectJson($body = '')
    {
        if (!is_string($body)) {
            throw new SdkException(ExceptionCodeMsg::MSG_HTTP_LIB_CODE_IS_CORRECT_JSON_1, ExceptionCodeMsg::CODE_HTTP_LIB_CODE_IS_CORRECT_JSON_1);
        }
        $content    = json_decode($body, true);
        $json_error = json_last_error();
        if (JSON_ERROR_NONE == $json_error) {
            return $content;
        }

        return false;
    }

    // windows系统
    public static function isWin()
    {
        if ('WIN' == strtoupper(substr(PHP_OS, 0, 3))) {
            return true;
        }

        return false;
    }

    //获取当前时间
    public static function getCurDateTime($format = 'Y-m-d H:i:s')
    {
        return date($format);
    }

    /**
     * @param $value
     * @param string $logFile
     * @node_name
     *
     * @see
     * @desc
     */
    public static function logSdk($value, $logFile = '')
    {
        if (self::isWin()) {
            $file = 'C:/sdk.log';
        } else {
            $file = '/tmp/sdk.log';
        }
        if (!empty($logFile) && file_exists(dirname($logFile))) {
            $file = $logFile;
        }
        if (is_array($value)) {
            file_put_contents($file, self::getCurDateTime().':'.print_r($value, true)."\n", FILE_APPEND);
        } elseif (is_string($value)) {
            file_put_contents($file, self::getCurDateTime().':'.$value."\n", FILE_APPEND);
        } elseif (is_numeric($value)) {
            file_put_contents($file, self::getCurDateTime().':'.$value."\n", FILE_APPEND);
        } elseif (is_object($value)) {
            file_put_contents($file, self::getCurDateTime().':'.print_r($value, true)."\n", FILE_APPEND);
        }
    }
}
