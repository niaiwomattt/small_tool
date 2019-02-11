<?php
function post($url, $data){//file_get_content
    $postdata = http_build_query($data);
    $opts = ['http' => [
        'method'  => 'POST',
        'timeout'  => '300',
        'header'  => 'Content-type: application/x-www-form-urlencoded', 'content' => $postdata ]
    ];
    $context = stream_context_create($opts);
    $result = file_get_contents($url, false, $context);
    return $result;
}

$str = file_get_contents('./clients.json');
$data = json_decode($str, true);
$post = ['data' => $data['RECORDS']];

$ret = post('http://admin.essagent.com/Business/loadApi', $post);
var_dump($ret);