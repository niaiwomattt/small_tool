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
        // 如果请求体存在 scroll_id 直接执行，如果不存在，去拼接。
        if (isset($this->inData['view']['scroll_id'])) {
            $this->method = 'GET';// 不存在 body ，直接用GET
            $purl = parse_url($this->url);
            $tmpUrl = $purl['scheme']."://".$purl['host'].':'.$purl['port'];
            $this->setUrl( $tmpUrl."/_search/scroll?scroll=1m&scroll_id=" . $this->inData['view']['scroll_id']);
            return $this;
        }
        // 有查询拼接查询
        if (empty($this->inData['cond']) ) {
            $this->error( 2, '没有查询数据！');
        }
        $this->pullData = ['query'=>[]];
        $this->pullData['query']['bool'] = $this->_valFormat($this->inData['cond']);

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
        if (isset($this->inData['view']['count'])) {
            $this->pullData['size'] = $this->inData['view']['count'];
        }
        // 对每个完整的查询添加 游标查询
        $this->setUrl($this->url."?scroll=1m");
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
            }else {
                $desc = 'DESC';
            }
            $ret[ $this->getRawName($val['name']) ]['order'] = $desc;
        }
        return $ret;
    }

    // 获取带 raw 的字段
    private function getRawName($name)
    {
        $arr = ['vname','hrscan_name','msscan','avpscan','esetscan','diescan'];
        if(in_array($name,$arr)){
            return $name.".raw";
        }
        return $name;
    }

    // 值格式化
    private function _valFormat($v)
    {
        // query.bool.[must,must_not,should]
        $query = [
            'must'=>[],
            'must_not'=>[],
            'should'=>[],
        ];

        if (array_key_exists('search',$v)) {
            $query['must'][]['query_string']['query'] = $v['search'];
        }

    	if (array_key_exists('id', $v)) {
            if (count($v['id']) > 1) {
                $query['must'][]['terms']['id'] = $v['id'];
            } else {
                $query['must'][]['term']['id'] = $v['id'][0];
            }
        }

        if (array_key_exists('source',$v)) {
            if (count($v['source']) > 1) {
                $query['must'][]['terms']['srclist'] = $v['source'];
            } else {
                $query['must'][]['term']['srclist'] = $v['source'][0];
            }
        }

        if (array_key_exists('hash', $v)) {
            switch(strlen($v['hash'][0]))
            {
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
                if (count($v['hash']) > 1) {
                    $query['must'][]['terms'][$hashtype] = $v['hash'];
                } else {
                    $query['must'][]['term'][$hashtype] = $v['hash'][0];
                }
            }
        }

        if (array_key_exists('filetype', $v)) {
            if (count($v['filetype']) > 1) {
                $query['must'][]['terms']['filetype'] = $v['filetype'];
            } else {
                $query['must'][]['term']['filetype'] = $v['filetype'][0];
            }
        }

        if (array_key_exists('filesize', $v)) {
            $tmpFsz = [];
            foreach ($v['filesize'] as $key=>$val) {
                switch ($key)
                {
                case 'gt':
                    $tmpFsz['gt'] = $val;
                    break;
                case 'lt':
                    $tmpFsz['lt'] = $val;
                    break;
                case 'eq':
                    $query['must'][]['term']['filesize'] = $val;
                    break;
                case 'ge':
                    $tmpFsz['gte'] = $val;
                    break;
                case 'le':
                    $tmpFsz['lte'] = $val;
                    break;
                }
            }
            if (!empty($tmpFsz)) {
                $query['must'][]['range']['filesize'] = $tmpFsz;
            }
        }

        if (array_key_exists('createtime', $v)) {
            $tmpAdd = [];
            foreach ($v['createtime'] as $key=>$val) {
                $val = date(DATE_RFC3339,strtotime($val));
                switch ($key)
                {
                case 'gt':
                    $tmpAdd['gt'] = $val;
                    break;
                case 'lt':
                    $tmpAdd['lt'] = $val;
                    break;
                case 'eq':
                    $query['must'][]['term']['addtime'] = $val;
                    break;
                case 'ge':
                    $tmpAdd['gte'] = $val;
                    break;
                case 'le':
                    $tmpAdd['lte'] = $val;
                    break;
                }
            }
            if (!empty($tmpAdd)) {
                $query['must'][]['range']['addtime'] = $tmpAdd;
            }

        }

        if (array_key_exists('modifytime', $v)) {
            $tmpMdf = [];
            foreach ($v['modifytime'] as $key=>$val) {
                $val = date(DATE_RFC3339,strtotime($val));
                switch ($key)
                {
                case 'gt':
                    $tmpFsz['gt'] = $val;
                    break;
                case 'lt':
                    $tmpFsz['lt'] = $val;
                    break;
                case 'eq':
                    $query['must'][]['term']['modtime'] = $val;
                    break;
                case 'ge':
                    $tmpFsz['gte'] = $val;
                    break;
                case 'le':
                    $tmpFsz['lte'] = $val;
                    break;
                }
            }
            if (!empty($tmpMdf)) {
                $query['must'][]['range']['modtime'] = $tmpMdf;
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

        if (array_key_exists('hrscan_name', $v)) {
            if (preg_match('/^[0-9a-fA-F]{16}$/', $v['hrscan_name'])) {
                $query['must'][]['term']['hrscan_id'] = $v['name'];
            } else {
                if ($v['hrscan_name']=="*") {
                    $query['must'][]['exists']['field'] = 'hrscan_name.raw';
                } else if ($v['hrscan_name']=="!") {
                    $query['must_not'][]['exists']['field'] = 'hrscan_name.raw';
                } else {
                    $query['must'][]['regexp']['hrscan_name.raw']['value'] = $v['hrscan_name'];
                }
            }
        }

        if (array_key_exists('msscan', $v)) {
            if ($v['msscan']=="*") {
                $query['must'][]['exists']['field'] = 'msscan.raw';
            } else if ($v['msscan']=="!") {
                $query['must_not'][]['exists']['field'] = 'msscan.raw';
            } else {
                $query['must'][]['regexp']['msscan.raw']['value'] = $v['msscan'];
            }

        }

        if (array_key_exists('avpscan', $v)) {
            if ($v['avpscan']=="*") {
                $query['must'][]['exists']['field'] = 'avpscan.raw';
            } else if ($v['avpscan']=="!") {
                $query['must_not'][]['exists']['field'] = 'avpscan.raw';
            } else {
                $query['must'][]['regexp']['avpscan.raw']['value'] = $v['avpscan'];
            }
        }

        if (array_key_exists('esetscan', $v)) {
            if ($v['esetscan']=="*") {
                $query['must'][]['exists']['field'] = 'esetscan.raw';
            } else if ($v['esetscan']=="!") {
                $query['must_not'][]['exists']['field'] = 'esetscan.raw';
            } else {
                $query['must'][]['regexp']['esetscan.raw']['value'] = $v['esetscan'];
            }
        }

        if (array_key_exists('diescan', $v)) {
            $query['must'][]['regexp']['diescan.raw']['value'] = $v['diescan'];
        }

        if (array_key_exists('simhash', $v)) {
            if (count($v['simhash']) > 1) {
                $query['must'][]['terms']['simhash'] = $v['simhash'];
            } else {
                $query['must'][]['term']['simhash'] = $v['simhash'][0];
            }
        }

        if (array_key_exists('hashsig', $v)) {
            if (count($v['hashsig']) > 1) {
                $query['must'][]['terms']['hashsig'] = $v['hashsig'];
            } else {
                $query['must'][]['term']['hashsig'] = $v['hashsig'][0];
            }
        }

        if (array_key_exists('status', $v)) {
            if (count($v['status']) > 1) {
                $query['must'][]['terms']['status'] = $v['status'];
            } else {
                $query['must'][]['term']['status'] = $v['status'][0];
            }
        }

        if (array_key_exists('result', $v)) {
            if (count($v['result']) > 1) {
                $query['must'][]['terms']['result'] = $v['result'];
            } else {
                $query['must'][]['term']['result'] = $v['result'][0];
            }
        }

        if (array_key_exists('analyst', $v)) {
            if (count($v['analyst']) > 1) {
                $query['must'][]['terms']['analyst'] = $v['analyst'];
            } else {
                $query['must'][]['term']['analyst'] = $v['analyst'][0];
            }
        }

        // wl_test 和 eng_test 是否存在
        if (array_key_exists('wl_test', $v) && array_key_exists('eng_test', $v)) {
            $query['must'][]['term']['tags.raw'] = "wltest";
            $query['must'][]['term']['tags.raw'] = 'engtest';
        }elseif (array_key_exists('wl_test', $v)) {
            $query['must'][]['term']['tags.raw'] = "wltest";
        }elseif (array_key_exists('eng_test', $v)) {
            $query['must'][]['term']['tags.raw'] = 'engtest';
        }

        return $query;
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
            // echo $this->pullData;exit;
            $request = $obj->request( $this->url, $this->method, $this->pullData, $this->headers, $this->options['auth']);
        }
        // echo $request['body'];exit;
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
                'view' => [
                    'total'=> $this->esData['hits']['total'],
                    'begin'=> $this->inData['view']['begin'],
                    'count'=> $this->inData['view']['count']
                ]

            ]
        ];
        // 输出结果加 scroll 参数
        if (isset($this->esData['_scroll_id']) ) {
            $this->reData['data']['view']['scroll_id'] = $this->esData['_scroll_id'];
        }

        foreach ($this->esData['hits']['hits'] as  $value) {
            $this->reData['data']['list'][] = $value['_source'];
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

