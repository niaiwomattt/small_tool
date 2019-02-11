<?php
// curl请求类
class myCurl
{
    private $_url = '';
    private $_followlocation = true;
    private $_timeout = 30;
    private $_maxRedirects = 4;
    private $_includeHeader = false;
    private $_binaryTransfer = false;

    private $auth = [];
    private $post = false;
    private $headers = [];
    private $data = '';

    // 构造
    public function __construct($url = '',$followlocation = true,$timeOut = 30,$maxRedirecs = 4,$binaryTransfer = false,$includeHeader = false,$noBody = false) 
    { 
        $this->_url = $url; 
        $this->_followlocation = $followlocation; 
        $this->_timeout = $timeOut; 
        $this->_maxRedirects = $maxRedirecs; 
        $this->_includeHeader = $includeHeader; 
        $this->_binaryTransfer = $binaryTransfer; 
    }

    // 对外执行的请求
    public function request( $url = '', $method = 'GET', $data = '', $headers = [], $auth = [])
    {
        $this->_url = $url;
        if ( $method == 'POST') {
            $this->post = true;                        
        }
        if ($data) {
            $this->data = $data;
        }
        if ($auth) {
            $this->headers[] = 'Authorization: Basic '.base64_encode("{$auth[0]}:{$auth[1]}");
            $this->headers[] = "{$auth[0]}:{$auth[1]}";
            $this->auth = $auth;
        }
        if ($headers) {
            foreach ($headers as $key => $value) {
                $this->headers[] = $key.': '.$value;
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
            CURLOPT_FOLLOWLOCATION => $this->_followlocation
        ];
        curl_setopt_array( $c, $options);
        if ($this->headers) {
            curl_setopt($c, CURLOPT_HTTPHEADER, $this->headers);
        }
        if ($this->post) {
            curl_setopt( $c, CURLOPT_POST, true);
            curl_setopt( $c, CURLOPT_POSTFIELDS, $this->data);
        }
        $ret = curl_exec($c);
        $retCode = curl_getinfo( $c, CURLINFO_HTTP_CODE);
        curl_close($c);
        return [
            'code' => $retCode,
            'body' => $ret 
        ];
    }
}

// 请求es类
class RequestsEs
{
    private $url = '';
    private $errMsg = [
        'errno' => 0,
        'errmsg'=> ''
    ];
    private $esData = [];
    private $reData = [];
    private $inData = [];
    private $pullData = [];
    private $headers = [];
    private $options = [];
    private $method  = '';


    // 输出错误信息
    public function error( $num = 0, $msg = '')
    {
        $this->errMsg['errno']  = $num;
        $this->errMsg['errmsg'] = $msg;
        $this->putData();
        return $this;
    }
    // 获取客户端数据
    public function getData()
    {
        $json =  file_get_contents("php://input");
        $deJson = json_decode( $json, true);
        //var_dump($deJson);
        if($deJson === null){
            $this->error( 1, '输入JSON格式不正确！');
        }
        $this->inData = $deJson;
        return $this;
    }

    // 设置账号密码
    public function setUserPwd( $user, $pwd)
    {
        $this->options['auth'] = [
            $user,$pwd
        ];
        return $this;
    }

    // *区别系统的 getHeaders
    // 获取header数据
    public function getHeaders2(Type $var = null)
    {
        $this->headers = [
            'Content-Type' => 'application/json'
        ];
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $this->method = $_SERVER['REQUEST_METHOD'];
        }else {
            $this->error( 7, '请携带请求模式信息[GET|POST]');
        }
        return $this;        
    }

    // 验证输入的数据是否符合格式
    public function validation()
    {
        // 有查询拼接查询
        if (empty($this->inData['cond']) ) {
            $this->error( 2, '没有查询数据！');
        }
        $this->pullData = ['query'=>[]];
        $this->pullData['query'] = $this->_valFormat($this->inData['cond']);
        
        // 有排序拼接排序
        if (!empty($this->inData['order']) && !is_array($this->inData['order'])){
            $this->error( 3, 'order必须是数组！');            
        }
        if (isset($this->inData['order'])) {
            $this->pullData['sort'] = $this->_orderFormat($this->inData['order']);            
        }

        // 有view拼接view
        if (!empty($this->inData['view']) && !is_array($this->inData['view'])){
            $this->error( 4, 'view必须是数组！');            
        }
        if (isset($this->inData['view']['begin'])) {
            $this->pullData['from'] = $this->inData['view']['begin'];                        
        }
        if (isset($this->inData['view']['count'])) {
            $this->pullData['size'] = $this->inData['view']['count'];                            
        }
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
            }else {
                $desc = 'DESC';
            }
            $ret[$val['name']]['order'] = $desc;
        }
        return $ret;
    }

    // 值格式化
    private function _valFormat($v)
    {
        $query = [
            'must'=>[],
            'must_not'=>[],
            'should'=>[],
        ];

        
        // 普通类型
        $t = ['id','filetype'];
        // 哈希长度对应字段
        $h = [
            32 => 'md5',
            40 => 'sha1',
            64 => 'sha256',
            128 => 'sha512'
        ];
        // 逻辑判断字段
        $l = ['filesize','modifytime'];
        // 允许的逻辑判断符号
        $ll= [
            'gt' => true,
            'lt' => true,
            'eq' => true,
            'ge' => true,
            'le' => true,
        ];
        foreach ($v as $key => $val) {
            // 普通类型判断
            if (isset($t[$key])) {
                if (count($val) > 1) {
                    $query['must'][]['terms'][$key] = $val;
                } else {
                    $query['must'][]['term'][$key] = $val[0];
                }
            }
            // 哈希类型判断
            if ($key == 'hash') {
                $hl = strlen($val[0]);
                $hashtype = isset($h[$hl])?$h[$hl]:'';
                if (!empty($hashtype)) {
                    if (count($val) > 1) {
                        $query['must'][]['terms'][$hashtype] = $val;
                    } else {
                        $query['must'][]['term'][$hashtype] = $val[0];
                    }
                }
            }
            // 逻辑类型判断
            if (isset($l[$key])) {
                $tmpL = [];
                foreach ($val as $kk => $vv) {
                    if (isset($ll[$kk])) {
                        $tmpL[][$kk] = $vv;
                    }
                }
                $query['must'][]['range'][$key] = $tmpL;
            }

            $alias = [
                ''
            ];
            // 正则类型判断
            if (isset($r[$key])) {
                if (preg_match('/^[0-9a-fA-F]{16}$/', $val)) {
                    $query['must'][]['term']['vid'] = $v['name'];                
                } else {
                    if ($v['vname']=="*") {
                        $query['must'][]['exists']['field'] = 'vname.raw';
                    } else if ($v['vname']=="!") {
                        $query['must_not'][]['exists']['field'] = 'vname.raw';                    
                    } else {
                        $query['must'][]['regexp']['vname.raw']['value'] = $v['vname'];                    
                    }
                }
            }
            if (array_key_exists('vname', $v)) {
                if (preg_match('/^[0-9a-fA-F]{16}$/', $v['vname'])) {
                    $query['must'][]['term']['vid'] = $v['name'];                
                } else {
                    if ($v['vname']=="*") {
                        $query['must'][]['exists']['field'] = 'vname.raw';
                    } else if ($v['vname']=="!") {
                        $query['must_not'][]['exists']['field'] = 'vname.raw';                    
                    } else {
                        $query['must'][]['regexp']['vname.raw']['value'] = $v['vname'];                    
                    }
                }
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
        if ( $obj == null) {
            $this->error( 10, 'curl 对象不存在');
        }
        $request = [];
        if ($this->method == 'GET') {
            $request = $obj->request( $this->url, $this->method, '', $this->headers, $this->options['auth']);
        }elseif ($this->method == 'POST') {
            $this->pullData = json_encode($this->pullData);
            $request = $obj->request( $this->url, $this->method, $this->pullData, $this->headers, $this->options['auth']);            
        }
        $deJson = json_decode( $request['body'], true) ;
        if($deJson == null){
            $this->error( 5, '返回JSON格式不正确！');
        }
        if ( isset($deJson['status']) && !($deJson['status'] >= 200 && $deJson['status'] < 300 ) ) {
            $this->error( 6, $deJson);
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
                'total'=>0,
                'begin'=> $this->inData['view']['begin'],
                'count'=> $this->inData['view']['count']
            ]
        ];
        foreach ($this->esData['hits']['hits'] as  $value) {
            $this->reData['data']['list'][] = $value['_source'];
            $this->reData['data']['total']++;
        }
        return $this;        
    }

    // 输出数据
    public function putData()
    {
        header('Content-type: application/json;charset=UTF-8');
        header('X-Powered-By: php pull ElasticSearch');
        if ( isset($this->esData['took'])) {
            header('X-ElasticSearch-took: '. $this->esData['took']);
        }
        if ($this->errMsg['errno'] != 0) {
            echo json_encode($this->errMsg);
        }else {
            echo  json_encode($this->reData);            
        }
        exit(0);
    }

    // 执行
    public function run( $url = '', $user, $pwd)
    {
        if ($url == '') {
            $this->error( 9, '请配置ES URL');
        }
        $this->setUrl($url)->setUserPwd( $user, $pwd)->getHeaders2()->getData()
        ->validation()->pull()->validation()
        ->formartReData()->putData();
    }
    
}
// 实例化并调用
$r = new \RequestsEs();
$r->run('http://192.168.1.21:9200/samples_v1/_search', 'elastic', 'QmXzT5BXU*iE+=p-?NGn');

