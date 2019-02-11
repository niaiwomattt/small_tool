<?php
/**
 * Request 请求类
 * 包含所有和请求相关的方法
 * @package Parse;
 */
namespace Vendor\Parse;

class Request extends Base
{
    protected $url     = '';
    protected $headers = [];
    protected $options = [];
    protected $method  = '';
    protected $inData  = [];
    protected $reData  = [];
    public $request  = [];

    /**
     * 设置账号密码
     * @param string $user  用户名
     * @param string $pwd   密码
     * @return object this对象
     */
    protected function setUserPwd($user = '', $pwd = '')
    {
        if ( empty($user)) {
            $this->error(11, "没有输入用户名");
        }
        $this->options['auth'] = [
            $user, $pwd,
        ];
        return $this;
    }

    /**
     *   *区别系统的 getHeaders
     * 获取header数据
     */
    protected function getHeaders2()
    {
        $this->headers = [
            'Content-Type' => 'application/json',
        ];

        if (isset($_SERVER['REQUEST_METHOD'])) {
            $this->method = $_SERVER['REQUEST_METHOD'];
        } else {
            $this->error(7, '请携带请求模式信息[GET|POST]');
        }
        return $this;
    }

    /**
     * 设置请求url
     * @param $url string url地址
     *
     */
    protected function setUrl($url = '')
    {
        if (empty($url)) {
            $this->error(9, '缺少 url');
        }
        $this->url = $url;
        return $this;
    }

    /**
     * 获取客户端数据并解析
     * @param
     */
    public function getDataClient($Parse = null)
    {
        if ($Parse == null) {
            $this->error(10, 'Parse 对象不存在');
        }
        $json   = file_get_contents('php://input');
        $deJson = json_decode($json, true);
        if ($deJson === null) {
            $this->error(1, '输入JSON格式不正确！');
        }
        $this->inData = $deJson;
        $this->pullData = $Parse->parse($this->inData);
        return $this;
    }

    /**
     * 发送数据到服务端
     * $Curl object Curl请求类
     * $data string 要发送的数据
     */
    public function sendDataServe($Curl = null, $url = '', $user = '', $pwd = '')
    {
        if ($Curl == null) {
            $this->error(10, 'Curl 对象不存在');
        }

        $this->setUrl($url);
        $this->getHeaders2();
        $this->setUserPwd($user, $pwd);

        // 如果请求体存在 scroll_id 直接执行 GET。
        if (isset($this->inData['view']['scroll_id'])) {
            $this->method = 'GET'; // 不存在 body ，直接用GET
            $purl = parse_url($this->url);
            $tmpUrl = $purl['scheme'] . '://' . $purl['host'] . ':' . $purl['port'];
            $this->setUrl($tmpUrl . '/_search/scroll?scroll=1m&scroll_id=' . $this->inData['view']['scroll_id']);
        }

        if ($this->method == 'GET') {
            $this->request = $Curl->request($this->url, $this->method, '', $this->headers, $this->options['auth']);
        } elseif ($this->method == 'POST') {
            // $this->pullData 来源于 getDataClient 中 $Parse->parse($this->inData); 解析得到
            $this->request = $Curl->request($this->url, $this->method, json_encode($this->pullData), $this->headers, $this->options['auth']);
        }

        return $this;
    }

    /**
     * 格式化并返回数据
     */
    public function formartReData()
    {
        // 有错直接返回
        if (!empty($this->request['errno'])) {
            $this->putData();
        }
        // echo $this->request['body'];exit;
        $deJson = json_decode($this->request['body'], true);
        if ($deJson == null) {
            $this->error(5, '返回JSON格式不正确！');
        }
        if (isset($deJson['status']) && !($deJson['status'] >= 200 && $deJson['status'] < 300)) {
            $this->error(6, $deJson);
        }
        $this->reData = [
            'errno' => 0,
            'data'  => [
                'list' => [],
                'view' => [
                    'total' => $deJson['hits']['total'],
                    'begin' => $this->inData['view']['begin'],
                    'count' => $this->inData['view']['count'],
                ],
            ],
        ];
        // 输出结果加 scroll 参数
        if (isset($deJson['_scroll_id'])) {
            $this->reData['data']['view']['scroll_id'] = $deJson['_scroll_id'];
        }

        // 格式化 ISO8601 格式的时间为 Y-m-d H:i:s 格式
        foreach ($deJson['hits']['hits'] as $value) {
            $value['_source']['addtime']    = date('Y-m-d H:i:s', strtotime($value['_source']['addtime']));
            $value['_source']['modtime']    = date('Y-m-d H:i:s', strtotime($value['_source']['modtime']));
            $this->reData['data']['list'][] = $value['_source'];
        }

        $this->putData();
        return $this;
    }

}
