<?php
// require './vendor/rmccue/requests/library/Requests.php';
require './vendor/autoload.php';
class PhpRequestsEs
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

    // *区别系统的 getHeaders
    // 获取header数据
    public function getHeaders2(Type $var = null)
    {
        $this->headers = [
            'Content-Type' => 'application/json'
        ];
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $this->headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        }else {
            $this->error( 7, '请携带基本验证信息！');
        }
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
        $ret = [];
        $key = [];
        $val = [];
        foreach ($v as $ka => $kv) {
            $key = $ka;
            $val = $kv;
        }
        $tarr = [
            'filesize','createtime','modifytime'
        ];
        if (in_array( $key, $tarr)) {
            $ret['range'][$key] = $val;
            return $ret;
        }
        if (!is_array($val)) {
            $myk = $key.".raw";
            $ret['term'][$myk] = $val;                        
        }
        if (is_array($val)) {
            foreach ($val as $kk => $vv) {
                $ret['terms'][$key][] = $vv;
            }
        }
        return $ret;
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
        Requests::register_autoloader();
        if ($this->method == 'GET') {
            $request = Requests::get( $this->url, $this->headers, $this->options);
        }elseif ($this->method == 'POST') {
            $this->pullData = json_encode($this->pullData);
            $request = Requests::post( $this->url, $this->headers, $this->pullData, $this->options);
        }
        $deJson = json_decode( $request->body, true) ;
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
    public function run($url = '')
    {
        if ($url == '') {
            $this->error( 9, '请配置ES URL');
        }
        $this->setUrl($url)->getHeaders2()->getData()
        ->validation()->pull()->validation()
        ->formartReData()->putData();
    }
    
}

$r = new \PhpRequestsEs();
$r->run('http://192.168.1.21:9200/samples_v1/_search');

