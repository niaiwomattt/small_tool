<?php
/**
 * Curl 请求类
 * 封装 Curl 请求函数
 * @package Parse;
 */
namespace Vendor\Parse;

class Curl
{
    private $url = '';
    private $followLocation = true;
    private $timeOut = 30;
    private $maxRedirects = 4;
    private $includeHeader = false;
    private $binaryTransfer = false;

    private $auth = [];
    private $post = false;
    private $headers = [];
    private $data = '';

    /**
     * 构造
     */
    public function __construct($url = '', $followlocation = true, $timeOut = 30, $maxRedirecs = 4, $binaryTransfer = false, $includeHeader = false)
    {
        $this->url = $url;
        $this->followLocation = $followlocation;
        $this->timeOut = $timeOut;
        $this->maxRedirects = $maxRedirecs;
        $this->includeHeader = $includeHeader;
        $this->binaryTransfer = $binaryTransfer;
    }

    /**
     * 对外执行的请求
     */
    public function request($url = '', $method = 'GET', $data = '', $headers = [], $auth = [])
    {
        $this->url = $url;
        if ($method == 'POST') {
            $this->post = true;
        }
        if ($data) {
            $this->data = $data;
        }
        if ($auth) {
            $this->headers[] = 'Authorization: Basic ' . base64_encode("{$auth[0]}:{$auth[1]}");
            $this->headers[] = "{$auth[0]}:{$auth[1]}";
            $this->auth = $auth;
        }
        if ($headers) {
            foreach ($headers as $key => $value) {
                $this->headers[] = $key . ': ' . $value;
            }
        }
        return $this->createCurl();
    }

    /**
     * 创建 Curl 请求
     */
    private function createCurl()
    {
        $c = curl_init();
        $options = [
            CURLOPT_URL => $this->url,
            CURLOPT_HTTPHEADER => ['Expect:'],
            CURLOPT_TIMEOUT => $this->timeOut,
            CURLOPT_MAXREDIRS => $this->maxRedirects,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $this->followLocation,
        ];
        curl_setopt_array($c, $options);
        if ($this->headers) {
            curl_setopt($c, CURLOPT_HTTPHEADER, $this->headers);
        }
        if ($this->post) {
            curl_setopt($c, CURLOPT_POST, true);
            curl_setopt($c, CURLOPT_POSTFIELDS, $this->data);
        }
        $ret = curl_exec($c);
        $retCode = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        return [
            'code' => $retCode,
            'body' => $ret,
        ];
    }
}
