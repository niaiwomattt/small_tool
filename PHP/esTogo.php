<?php
/**
 * json 转换到 golang 结构体
 * 执行 php -S 0.0.0.0:8686 esTogo.php
 * 浏览器访问 localhost:8686 访问当前页面。
 */

$json = '这里放json';
$gostruct = "这里放go的struct";
$keyList = [];
if($_POST){
    $null = $_POST['sex'];
    echo putHtml( trunJson($_POST['json'], $null), $_POST['json'] );
}else{
    echo putHtml($gostruct);
}

/*************** 定义部分 ******************/
// 转换json为golang结构体
function trunJson( $struct, $null, $name = 'Top') {
    // 未转换进行转换
    if (!is_array($struct)) {
        $struct = json_decode($struct, true);
    }
    $str = ''; // 转换完的
    $array = []; // 数组，需要递归转换的
    // 循环顶层结构
    foreach ($struct as $key => $value) {
        if ($value === null && $null == 0) {
            die("不允许值中出现null");
        }elseif ( is_array($value) ) {
            // 不是关联数组，又子元素又不是数组的，直接取最终类型 []int 
            if (!_isAssocArray($value) && !is_array($value[0])) {
                $str .= getUpName( $key, true)."  []".getTypeMy($value)."  `json:\"{$key}\"`\n";                
            }else {
                // 是数组的和关联数组的 变成[]Sort 这样的
                $array[$key] = $value;
                $str .= getUpName( $key, true)."  ".getAray($value).getUpName($key, false, true)."  `json:\"{$key}\"`\n";    
            }
        }else {
            $str .= getUpName( $key, true)."  ".getTypeMy($value)."  `json:\"{$key}\"`\n";
        }
    }
    $str = rtrim($str);
    $str = "\ntype {$name} struct {\n".$str."\n}";

    // 循环数组和对象
    foreach ($array as $k => $v) {
        if (getAray($v) && is_array($v[0])) {
            $tmp = $v[0];
            $v = $tmp;
        }elseif(getAray($v)) {
            $str .= "\ntype  ".getUpName($k)." struct {\n  ".getTypeMy($v[0])."  `json:\"{$k}\"`\n}";
            continue;
        }
        // 递归调用
        $str .= trunJson($v,$null,getUpName($k) );
    }
    return $str;
}

// 返回首字母大写后的名字
function getUpName( $name, $nospace=false, $nameRepeat=false) {
    global $keyList;
    $tmpName = $name;
    // 检测名字是否重复，重复给名字后面加数字
    if ($nameRepeat) {
        if (!empty($keyList[$name])) {
            $tmpName = $tmpName.count($keyList[$name]);
        }
        $keyList[$name][] = 1;      
    }elseif(!empty($keyList[$name]) && count($keyList[$name])>1) {
        $tmpName .= count($keyList[$name])-1;
    }
    // 去除名字前面的特殊字符，并大写首字母
    $tmpName = preg_replace('/^([0-9\_\s]+)/','',$tmpName);
    $tmpName = ucfirst($tmpName);
    if ($nospace) {
        $tmpName = '  '.sprintf("%-10s",$tmpName);
    }
    return $tmpName;
}

// 返回类型前缀
function getAray($arr ) {
    if (_isAssocArray($arr)) {
        return '';
    }
    return '[]';
}
// 判断是否是关联数组
function _isAssocArray(array $var)  
{  
    return array_diff_assoc(array_keys($var), range(0, sizeof($var))) ? TRUE : FALSE;  
}  

// 返回值的类型
function getTypeMy($val) {
    $type = gettype($val);
    switch ( $type) {
        case 'boolean':
            return 'bool';
        case 'integer':
            return 'int';
        case 'string':
            return 'string';
        case 'double':
            return 'float64';
            break;
        case 'array':
            if (!_isAssocArray($val) && is_array($val[0])) {
                return '[]';                
            }elseif (!_isAssocArray($val) && !is_array($val[0])) {
                return getTypeMy($val[0]);                
            }
        case 'NULL';
        case 'unknown type':
            return 'interface {}';
        case 'object':
            die("不能出现 object");
        default:
            return $type;
            break;
    }
}

// 输出html代码
function putHtml($gostruct, $json='') {
    return <<<P
    <!DOCTYPE html>
<html>
<head>
    <title>json结构转golang结构</title>
</head>
<body>
    <center>
        <h1>Json结构转Golang结构</h1>
        <form action="" method="post">
        <lable>是否允许null  </lable><input type="radio" name="sex" value="0" checked>否<input type="radio" name="sex" value="1">是
        <br/>
        <textarea name="json" rows="20" cols="100">
        {$json}
        </textarea>
        <br/>
        <input type="submit" height="20px" width="50px" value="转换">
        <br/>
        <textarea rows="20" cols="100">
        {$gostruct}
        </textarea>
        </form>
    </center>
</body>
</html>
P;

}