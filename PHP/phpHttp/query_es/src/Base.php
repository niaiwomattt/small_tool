<?php
/**
 * Parse 包的 base依赖
 * 封装错误输出和标准返回函数
 * @package Parse;
 */
namespace Vendor\Parse;

class Base
{
    protected $errMsg = [
        'errno' => 0,
        'errmsg' => ''
    ];
    protected $reData = [];

    // 输出错误
    protected function error($num = 0, $msg = '')
    {
        $this->errMsg['errno']  = $num;
        $this->errMsg['errmsg'] = $msg;
        $this->putData();
    }

    // 输出数据
    protected function putData()
    {
        header('Content-type: application/json;charset=UTF-8');
        header('X-Powered-By: php pull ElasticSearch');
        // if (isset($this->esData['took'])) {
        //     header('X-ElasticSearch-took: ' . $this->esData['took']);
        // }
        if ($this->errMsg['errno'] != 0) {
            echo json_encode($this->errMsg);
        } else {
            echo json_encode($this->reData);
        }
        exit($this->errMsg['errno']);
    }

}
