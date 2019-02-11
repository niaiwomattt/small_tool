<?php
/**
 * 解析类
 * 封装所有和解析相关的方法，解析客户端的 json 结构到 ElasticSearch DSL 结构
 * @package Parse;
 */
namespace Vendor\Parse;

class Parse extends Base
{
    protected $inData = [];
    protected $pullData = [];
    protected $query  = [];
    protected $queryWEstatus = false; //queryWE 函数处理状态

    /**
     * 解析输入数据到 ElasticSearch DSL格式
     * @param array $inData 客户端输入的json
     * @return array 解析完成返回ElasticSearch格式DSL
     */
    public function parse($inData = [])
    {
        $this->inData = $inData;
        $this->valFormat();
        $this->orderFormat();
        $this->pageFormat();
        return $this->pullData;
    }

    /**
     * 分页格式化
     */
    protected function pageFormat()
    {
        if (!empty($this->inData['view']) && !is_array($this->inData['view'])) {
            $this->error(4, 'view必须是数组！');
        }
        if (isset($this->inData['view']['count'])) {
            $this->pullData['size'] = $this->inData['view']['count'];
        }
        $this->pullData['from'] = 0;
    }

    /**
     * 排序格式化
     */
    protected function orderFormat()
    {
        if (!isset($this->inData['order'])) {
            return $this;
        }

        if (!empty($this->inData['order']) && !is_array($this->inData['order'])) {
            $this->error(3, 'order必须是数组！');
        }

        $desc = '';
        foreach ($this->inData['order'] as $key => $val) {
            if ($val['desc'] == 0) {
                $desc = 'ASC';
            } else {
                $desc = 'DESC';
            }
            $this->pullData['sort'][$this->getRawName($val['name'])]['order'] = $desc;
        }

        return $this;
    }

    /**
     * 获取带 raw 的字段
     */
    protected function getRawName($name)
    {
        $arr = ['vname', 'hrscan_name', 'msscan', 'avpscan', 'esetscan', 'diescan', 'tags'];
        if (in_array($name, $arr)) {
            return $name . '.raw';
        }

        return $name;
    }

    /**
     * 查询字段格式化，所有字段处理
     */
    protected function valFormat()
    {
        if (empty($this->inData['cond'])) {
            $this->pullData = ['query' => []];
            $this->pullData['query']['match_all'] = (object) [];

            return;
        }
        // 字段 => 对应的处理函数
        $arr = [
            'search'      => 'querySearch',

            'id'          => 'queryTerm',
            'filetype'    => 'queryTerm',
            'simhash'     => 'queryTerm',
            'hashsig'     => 'queryTerm',
            'status'      => 'queryTerm',
            'result'      => 'queryTerm',
            'analyst'     => 'queryTerm',

            'source'      => 'querySource',

            'wl_test'     => 'queryWE',
            'eng_test'    => 'queryWE',

            'hash'        => 'queryHash',

            'filesize'    => 'queryComparison',
            'createtime'  => 'queryComparison',
            'modifytime'  => 'queryComparison',

            'msscan'      => 'queryRegexp',
            'avpscan'     => 'queryRegexp',
            'esetscan'    => 'queryRegexp',
            'diescan'     => 'queryRegexp',

            'vname'       => 'queryNumString',
            'hrscan_name' => 'queryNumString',
        ];
        foreach ($this->inData['cond'] as $key => $value) {
            if (array_key_exists($key, $arr)) {
                $funName = $arr[$key];
                $this->$funName($key, $this->inData['cond']);
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

    /**
     * 获取 search 匹配
     */
    protected function querySearch($key, $v)
    {
        if (array_key_exists('search', $v)) {
            $this->query['must'][]['query_string']['query'] = $v['search'];
        }
    }

    /**
     * 获取 term 匹配
     */
    protected function queryTerm($key, $v)
    {
        if (count($v[$key]) > 1) {
            $this->query['must'][]['terms'][$key] = $v[$key];
        } else {
            $this->query['must'][]['term'][$key] = $v[$key][0];
        }
    }

    /**
     * wl_test 和 eng_test 是否存在
     */
    protected function queryWE($key, $v)
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

    /**
     * 查询Hash 类型
     */
    protected function queryHash($key, $v)
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

    /**
     * 获取和传入字段不同的字段
     */
    protected function getChangedName($key)
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

    /**
     * 是否是时间字段
     */
    protected function getIsDate($key)
    {
        $arr = [
            'createtime', 'modifytime',
        ];
        if (in_array($key, $arr)) {
            return true;
        }

        return false;
    }

    /**
     * 带有比较操作的查询
     */
    protected function queryComparison($key, $v)
    {
        $tmp = [];
        foreach ($v[$key] as $fk => $fv) {
            if ($this->getIsDate($key)) {
                // ISO8601 标准格式，Logstash 使用的格式
                $fv = date(\DateTime::ATOM, strtotime($fv));
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

    /**
     * 查询正则字段
     */
    protected function queryRegexp($key, $v)
    {
        if ($v[$key] == '*') {
            $this->query['must'][]['exists']['field'] = $key . '.raw';
        } elseif ($v[$key] == '!') {
            $this->query['must_not'][]['exists']['field'] = $key . '.raw';
        } else {
            $this->query['must'][]['regexp'][$key . '.raw']['value'] = $v[$key];
        }
    }

    /**
     * 获取数值字段名
     */
    protected function getNumField($key)
    {
        $arr = [
            'vname'       => 'vid',
            'hrscan_name' => 'hrscan_id',
        ];
        if (array_key_exists($key, $arr)) {
            return $arr[$key];
        }

        return $key;
    }

    /**
     * 查询包含数字和字符串的字段
     */
    protected function queryNumString($key, $v)
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

}
