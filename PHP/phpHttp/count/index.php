<?php
// 声明
function putCsv($data)
{
    $fileName = 'Aggs_'.date('YmdHis');
    header("Content-type:text/csv");
    header("Content-Disposition:attachment;filename=$fileName.csv");
    header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
    header('Expires:0');
    header('Pragma:public');
    ob_start();
    echo iconv('UTF-8', 'GBK', '字段,统计总数'."\r\n");
    if (!$data) {
        echo iconv('UTF-8', 'GBK', '无数据');
    }
    if (empty($data[0]['key'])) {
        unset($data[0]);
    }
    foreach ($data as $key => $val) {
        echo implode(',', array($val['key'],$val['doc_count'])) . "\r\n";
    }
    flush();
}

// 输出视图
function display($tpl, array $data = []) {
    //注入变量
    // extract($data);
    //引入模板
    if(!file_exists($tpl)) {
        throw new \UnexpectedValueException("视图{$tpl}不存在");
    }
    include $tpl;
    exit;
}

// 获取ES数据
function getEsData($url, $user, $pwd, $query)
{
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nContent-Length:". strlen($query). "\r\n",
            'content'=> $query
        ]
    ];

    $auth = '';
    if ($user) {
        $auth = base64_encode("$user:$pwd");
    }

    $context = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

// 解析数据到es DSL
function parse($data)
{
    $query = [
        'size' => 0,
        'query' => [
            'bool' => [
                'must' => [
                    [
                        'match_all' => (object) []
                    ]
                ]
            ]
        ]
    ];
    if ($_POST['query_string']) {

        $query['query']['bool']['must'][] = [

            'query_string' => [
                'query' => $_POST['query_string']
            ]
        ];
    }

    if ($_POST['scanner']) {
        $query['aggregations'] = [
                'name'=> [
                    'terms' => [
                        'field' => $_POST['scanner'],
                        'size' => 65535
                    ]
                ]
        ];
    }
    return $query;
}

// 调用
$baseDir = __DIR__;
if (empty($_POST)) {
    display($baseDir.'/search.html');
}
$query = parse($_POST);
$data  =json_decode(
    getEsData(
        'http://192.168.1.21:9200/samples_v1/_search',
        'elastic',
        'QmXzT5BXU*iE+=p-?NGn',
        json_encode($query)
    ),
    true
) ;
putCsv($data['aggregations']['name']['buckets']);

