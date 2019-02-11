<?php
/**
 * 执行 php -S 0.0.0.0:8686 mysqlToes.php 
 * 然后访问 localhost:8686?table=tablename
 * 查看源代码，得到格式化的es的json mapping 结构。
 */

/***************************** 数据库处理部分 ****************************/
$host = '192.168.1.20';
$dbname = 'Samples';
$user = 'sampr';
$pwd = 'sampR123#';

$conn = new mysqli($host,$user,$pwd,$dbname);
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

$table = trim($_GET['table']);
$sql = "desc {$table};";
$result = $conn->query($sql);

$data = [];
if ($result->num_rows > 0) {
    // 输出数据
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
        //echo "id: " . $row["id"]. " - Name: " . $row["firstname"]. " " . $row["lastname"]. "<br>";
    }
} else {
    echo "0 结果";
}

$redata = myTOGo($data);
// 输出格式化的 json数据
$json = json_encode($redata,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
$struct = str_replace(':',' ',$json);
$struct = str_replace('"','',$struct);
$struct = str_replace(',','',$struct);
$struct = str_replace('&a',':',$struct);
$struct = str_replace('&b','"',$struct);
echo $struct;
$conn->close();



/***************************** 转换部分 ******************************************/
function myTOGo(array $arr = []) {
    // 支持的转换类型
    $types = [
        "char"    => 'string', // char 放到 varchar上面，要不然char的结果会覆盖varchar的结果
        "varchar" => 'string',
        "int"     => 'int',
        "tinyint" => 'int',
        "datetime" => 'string'
    ];
    $goyy = [];
    $es_type = [];
    foreach ($arr as $value) {
        foreach ($types as $k => $v) {
            if (stripos($value['Type'],$k) === false) {
                continue;
            }else{
                $upf = ucfirst($value['Field']);
                $goyy[] = "&tmp.".$upf;
                $es_type[$upf] = $v.'  `json&a&b'.$value['Field'].'&b `';
            }
        }
        
    }
    $goyy = array_unique($goyy);
    echo implode(',',$goyy);
    return $es_type;
}
