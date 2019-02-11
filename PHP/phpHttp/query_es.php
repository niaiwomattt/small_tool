<?php

// curl请求类
class myCurl
{
    private $_url = '';
    private $_query_string = '';
    private $_followlocation = true;
    private $_timeout = 30;
    private $_maxRedirects = 4;
    private $_includeHeader = false;
    private $_binaryTransfer = false;

    private $auth = [];
    private $post = false;
    private $headers = [];
    private $data = '';

    /**
     *
     */
    public function __construct($url = '', $auth = [])
    {
        $this->_url = $url;
        $this->_auth = $auth;
    }

    /**
     * 设置特殊选项
     */
    public function setOption($followlocation = true, $timeOut = 30, $maxRedirecs = 4, $binaryTransfer = false, $includeHeader = false, $noBody = false)
    {
        $this->_followlocation = $followlocation;
        $this->_timeout = $timeOut;
        $this->_maxRedirects = $maxRedirecs;
        $this->_includeHeader = $includeHeader;
        $this->_binaryTransfer = $binaryTransfer;
    }

    // 对外执行的请求
    public function request($url = '', $method = 'GET', $data = '', $headers = [], $auth = [])
    {
        $this->_url = $url;
        if ($method == 'POST') {
            $this->post = true;
        }
        if ($data) {
            $this->data = $data;
        }
        if ($auth) {
            $this->auth = $auth;
            $this->headers[] = 'Authorization: Basic ' . base64_encode("{$auth[0]}:{$auth[1]}");
            $this->headers[] = "{$auth[0]}:{$auth[1]}";
        }
        if ($headers) {
            foreach ($headers as $key => $value) {
                $this->headers[] = $key . ': ' . $value;
            }
        }

        return $this->createCurl();
    }

    // 创建
    public function createCurl()
    {
        $c = curl_init();
        $options = [
            CURLOPT_URL => $this->_url,
            CURLOPT_HTTPHEADER => ['Expect:'],
            CURLOPT_TIMEOUT => $this->_timeout,
            CURLOPT_MAXREDIRS => $this->_maxRedirects,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $this->_followlocation,
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

// 请求es类
class RequestsEs
{
    private $url = '';
    private $errMsg = [
        'errno' => 0,
        'errmsg' => '',
    ];
    private $esData = [];
    private $reData = [];
    private $inData = [];
    private $pullData = [];
    private $headers = [];
    private $options = [];
    private $method = '';
    private $query = [];
    private $queryWEstatus = false; //queryWE 函数处理状态

    // 输出错误信息
    public function error($num = 0, $msg = '')
    {
        $this->errMsg['errno'] = $num;
        $this->errMsg['errmsg'] = $msg;
        $this->putData();

        return $this;
    }

    // 获取客户端数据
    public function getData()
    {
        $json = file_get_contents('php://input');
        $deJson = json_decode($json, true);
        //var_dump($deJson);
        if ($deJson === null) {
            $this->error(1, '输入JSON格式不正确！');
        }
        $this->inData = $deJson;

        return $this;
    }

    // 设置账号密码
    public function setUserPwd($user, $pwd)
    {
        $this->options['auth'] = [
            $user, $pwd,
        ];

        return $this;
    }

    // *区别系统的 getHeaders
    // 获取header数据
    public function getHeaders2(Type $var = null)
    {
        $this->headers = [
            'Content-Type' => 'application/json',
        ];
        // if (isset($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_USER']) ) {
        //     $this->options['auth'] = [
        //         $_SERVER['PHP_AUTH_USER'],
        //         $_SERVER['PHP_AUTH_PW']
        //     ];
        // }else {
        //     $this->error( 7, '请携带账号密码信息');
        // }
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $this->method = $_SERVER['REQUEST_METHOD'];
        } else {
            $this->error(7, '请携带请求模式信息[GET|POST]');
        }

        return $this;
    }

    // 验证输入的数据是否符合格式
    public function validation()
    {
        // 如果请求体存在 scroll_id 直接执行，如果不存在，去拼接。
        if (isset($this->inData['view']['scroll_id'])) {
            $this->method = 'GET'; // 不存在 body ，直接用GET
            $purl = parse_url($this->url);
            $tmpUrl = $purl['scheme'] . '://' . $purl['host'] . ':' . $purl['port'];
            $this->setUrl($tmpUrl . '/_search/scroll?scroll=1m&scroll_id=' . $this->inData['view']['scroll_id']);

            return $this;
        }
        // 有查询拼接查询
        $this->_valFormat($this->inData['cond']);

        // 有排序拼接排序
        if (!empty($this->inData['order']) && !is_array($this->inData['order'])) {
            $this->error(3, 'order必须是数组！');
        }
        if (isset($this->inData['order'])) {
            $this->pullData['sort'] = $this->_orderFormat($this->inData['order']);
        }

        // 有view拼接view
        if (!empty($this->inData['view']) && !is_array($this->inData['view'])) {
            $this->error(4, 'view必须是数组！');
        }
        if (isset($this->inData['view']['count'])) {
            $this->pullData['size'] = $this->inData['view']['count'];
        }
        // 对每个完整的查询添加 游标查询
        $this->setUrl($this->url . '?scroll=1m');
        $this->pullData['from'] = 0;

        // // 正常分页的参数
        // if (isset($this->inData['view']['begin'])) {
        //     $this->pullData['from'] = $this->inData['view']['begin'];
        // }

        return $this;
    }

    // 排序格式化
    private function _orderFormat($v)
    {
        $ret = [];
        $desc = '';
        foreach ($v as $key => $val) {
            if ($val['desc'] == 0) {
                $desc = 'ASC';
            } else {
                $desc = 'DESC';
            }
            $ret[$this->getRawName($val['name'])]['order'] = $desc;
        }

        return $ret;
    }

    // 获取带 raw 的字段
    private function getRawName($name)
    {
        $arr = ['vname', 'hrscan_name', 'msscan', 'avpscan', 'esetscan', 'diescan', 'tags'];
        if (in_array($name, $arr)) {
            return $name . '.raw';
        }

        return $name;
    }

    // 所有字段处理
    private function _valFormat($v)
    {
        if (empty($v)) {
            $this->pullData = ['query' => []];
            $this->pullData['query']['match_all'] = (object) [];

            return;
        }
        // 字段 => 对应的处理函数
        $arr = [
            'search' => 'querySearch',

            'id' => 'queryTerm',
            'filetype' => 'queryTerm',
            'simhash' => 'queryTerm',
            'hashsig' => 'queryTerm',
            'status' => 'queryTerm',
            'result' => 'queryTerm',
            'analyst' => 'queryTerm',

            'source' => 'querySource',

            'wl_test' => 'queryWE',
            'eng_test' => 'queryWE',

            'hash' => 'queryHash',

            'filesize' => 'queryComparison',
            'createtime' => 'queryComparison',
            'modifytime' => 'queryComparison',

            'msscan' => 'queryRegexp',
            'avpscan' => 'queryRegexp',
            'esetscan' => 'queryRegexp',
            'diescan' => 'queryRegexp',

            'vname' => 'queryNumString',
            'hrscan_name' => 'queryNumString',
        ];
        foreach ($v as $key => $value) {
            if (array_key_exists($key, $arr)) {
                $funName = $arr[$key];
                $this->$funName($key, $v);
            }
        }
        $this->pullData = ['query' => ['bool' => []]];
        $this->pullData['query']['bool'] = $this->query;

        return;
    }

    /**
     * 获取 Source 匹配
     */
    protected function querySource($key, $v)
    {
        if (array_key_exists('source',$v)) {
            if (count($v['source']) > 1) {
                $this->query['must'][]['terms']['srclist'] = $v['source'];
            } else {
                $this->query['must'][]['term']['srclist'] = $v['source'][0];
            }
        }
    }

    // 获取 search 匹配
    private function querySearch($key, $v)
    {
        if (array_key_exists('search', $v)) {
            $tmp = [
                "minimum_should_match" => "100%",
                "time_zone" => 'PRC',
                'query' => $v['search'],
            ];
            $this->query['must'][]['query_string'] = $tmp;
        }
    }

    // 获取 term 匹配
    private function queryTerm($key, $v)
    {
        if (count($v[$key]) > 1) {
            $this->query['must'][]['terms'][$key] = $v[$key];
        } else {
            $this->query['must'][]['term'][$key] = $v[$key][0];
        }
    }

    // wl_test 和 eng_test 是否存在
    private function queryWE($key, $v)
    {
        // 只执行一次
        if ($this->queryWEstatus) {
            return;
        }
        if (array_key_exists('wl_test', $v) && array_key_exists('eng_test', $v)) {
            $this->query['must'][]['term']['tags.raw'] = 'wltest';
            $this->query['must'][]['term']['tags.raw'] = 'engtest';
        } elseif (array_key_exists('wl_test', $v)) {
            $this->query['must'][]['term']['tags.raw'] = 'wltest';
        } elseif (array_key_exists('eng_test', $v)) {
            $this->query['must'][]['term']['tags.raw'] = 'engtest';
        }
        // 处理完之后标记一下
        $this->queryWEstatus = true;
    }

    // 查询Hash 类型
    private function queryHash($key, $v)
    {
        switch (strlen($v[$key][0])) {
            case 32:
                $hashtype = 'md5';
                break;
            case 40:
                $hashtype = 'sha1';
                break;
            case 64:
                $hashtype = 'sha256';
                break;
            case 128:
                $hashtype = 'sha512';
                break;
            default:
                $hashtype = '';
                break;
        }
        if (!empty($hashtype)) {
            if (count($v[$key]) > 1) {
                $this->query['must'][]['terms'][$hashtype] = $v[$key];
            } else {
                $this->query['must'][]['term'][$hashtype] = $v[$key][0];
            }
        }
    }

    // 获取和传入字段不同的字段
    private function getChangedName($key)
    {
        $arr = [
            'createtime' => 'addtime',
            'modifytime' => 'modtime',
        ];
        if (array_key_exists($key, $arr)) {
            return $arr[$key];
        }

        return $key;
    }

    // 是否是时间字段
    private function getIsDate($key)
    {
        $arr = [
            'createtime', 'modifytime',
        ];
        if (in_array($key, $arr)) {
            return true;
        }

        return false;
    }

    // 带有比较操作的查询
    private function queryComparison($key, $v)
    {
        $tmp = [];
        foreach ($v[$key] as $fk => $fv) {
            if ($this->getIsDate($key)) {
                // ISO8601 标准格式，Logstash 使用的格式
                $fv = date(DateTime::ATOM, strtotime($fv));
            }
            switch ($fk) {
                case 'gt':
                    $tmp['gt'] = $fv;
                    break;
                case 'lt':
                    $tmp['lt'] = $fv;
                    break;
                case 'eq':
                    $reName = $this->getChangedName($key);
                    $this->query['must'][]['term'][$reName] = $fv;
                    break;
                case 'ge':
                    $tmp['gte'] = $fv;
                    break;
                case 'le':
                    $tmp['lte'] = $fv;
                    break;
            }
        }
        if (!empty($tmp)) {
            $reName = $this->getChangedName($key);
            $this->query['must'][]['range'][$reName] = $tmp;
        }
    }

    // 查询正则字段
    private function queryRegexp($key, $v)
    {
        if ($v[$key] == '*') {
            $this->query['must'][]['exists']['field'] = $key . '.raw';
        } elseif ($v[$key] == '!') {
            $this->query['must_not'][]['exists']['field'] = $key . '.raw';
        } else {
            $this->query['must'][]['regexp'][$key . '.raw']['value'] = $v[$key];
        }
    }

    // 获取数值字段名
    private function getNumField($key)
    {
        $arr = [
            'vname' => 'vid',
            'hrscan_name' => 'hrscan_id',
        ];
        if (array_key_exists($key, $arr)) {
            return $arr[$key];
        }

        return $key;
    }

    // 查询包含数字和字符串的字段
    private function queryNumString($key, $v)
    {
        if (preg_match('/^[0-9a-fA-F]{16}$/', $v[$key])) {
            $reName = $this->getNumField($key);
            $query['must'][]['term'][$reName] = $v[$key];
        } else {
            if ($v[$key] == '*') {
                $this->query['must'][]['exists']['field'] = $key . '.raw';
            } elseif ($v[$key] == '!') {
                $this->query['must_not'][]['exists']['field'] = $key . '.raw';
            } else {
                $this->query['must'][]['regexp'][$key . '.raw']['value'] = $v[$key];
            }
        }
    }

    // 设置请求Url
    public function setUrl($url = '')
    {
        $this->url = $url;

        return $this;
    }

    // 请求数据
    public function pull()
    {
        $obj = new \myCurl();
        if ($obj == null) {
            $this->error(10, 'curl 对象不存在');
        }
        $request = [];
        if ($this->method == 'GET') {
            $request = $obj->request($this->url, $this->method, '', $this->headers, $this->options['auth']);
        } elseif ($this->method == 'POST') {
            $this->pullData = json_encode($this->pullData);
            echo $this->pullData;
            exit;
            $request = $obj->request($this->url, $this->method, $this->pullData, $this->headers, $this->options['auth']);
        }
        // echo $request['body'];exit;
        $deJson = json_decode($request['body'], true);
        if ($deJson == null) {
            $this->error(5, '返回JSON格式不正确！');
        }
        if (isset($deJson['status']) && !($deJson['status'] >= 200 && $deJson['status'] < 300)) {
            $this->error(6, $deJson);
        }
        $this->esData = $deJson;

        return $this;
    }

    // 格式化返回数据
    public function formartReData(Type $var = null)
    {
        $this->reData = [
            'errno' => 0,
            'data' => [
                'list' => [],
                'view' => [
                    'total' => $this->esData['hits']['total'],
                    'begin' => $this->inData['view']['begin'],
                    'count' => $this->inData['view']['count'],
                ],
            ],
        ];

        // 输出结果加 scroll 参数
        if (isset($this->esData['_scroll_id'])) {
            $this->reData['data']['view']['scroll_id'] = $this->esData['_scroll_id'];
        }

        // 格式化 ISO8601 格式的时间为 Y-m-d H:i:s 格式
        foreach ($this->esData['hits']['hits'] as $value) {
            $value['_source']['addtime'] = date('Y-m-d H:i:s', strtotime($value['_source']['addtime']));
            $value['_source']['modtime'] = date('Y-m-d H:i:s', strtotime($value['_source']['modtime']));
            $this->reData['data']['list'][] = $value['_source'];
        }

        return $this;
    }

    // 输出数据
    public function putData()
    {
        header('Content-type: application/json;charset=UTF-8');
        header('X-Powered-By: php pull ElasticSearch');
        if (isset($this->esData['took'])) {
            header('X-ElasticSearch-took: ' . $this->esData['took']);
        }
        if ($this->errMsg['errno'] != 0) {
            echo json_encode($this->errMsg);
        } else {
            echo json_encode($this->reData);
        }
        exit(0);
    }

    // 执行
    public function run($url = '', $user, $pwd)
    {
        if ($url == '') {
            $this->error(9, '请配置ES URL');
        }

        $this->setUrl($url)
            ->setUserPwd($user, $pwd)
            ->getHeaders2()
            ->getData()
            ->validation()
            ->pull()
            ->validation()
            ->formartReData()
            ->putData();
    }
}

date_default_timezone_set('PRC');
// 实例化并调用
$r = new \RequestsEs();
$r->run('http://192.168.1.21:9200/samples_v1/_search', 'elastic', 'QmXzT5BXU*iE+=p-?NGn');
