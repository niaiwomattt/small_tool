<?php
ini_set('max_execution_time', '0');
/**
 * 
 */

/***************************** 数据库处理部分 ****************************/
$host = '192.168.1.20';
$dbname = 'Samples';
$user = 'sampr';
$pwd = 'sampR123#';
$table = 'sampinfos';

$conn = new mysqli($host,$user,$pwd,$dbname);
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

$table = trim($_GET['table'])?trim($_GET['table']):$table;

$min = 0;
$setup = 1000;
$max = 69322758;
loopSql($conn, $table, $min, $setup, $max);

$conn->close();

// 循环查询sql
function loopSql($conn, $table, &$min = 0, $setup = 10, &$max = 0)
{
    $start = $min;
    $end = $start + $setup;
    while($start < $max){
        $sql = "select * from {$table} where id>{$start} and id <{$end};";
        //echo $sql;exit;
        $result = $conn->query($sql);

        $data = '';
        if ($result->num_rows > 0) {
            // 输出数据
            while($row = $result->fetch_assoc()) {
                $data .= "{ \"index\" : { \"_index\" : \"samples_v6\", \"_type\" : \"sampinfos\", \"_id\" : \"{$row['id']}\" } }\n";
                $data .= json_encode($row) . "\n" ;
                //echo "id: " . $row["id"]. " - Name: " . $row["firstname"]. " " . $row["lastname"]. "<br>";
            }
            $start = $start + $setup;
            $end = $start + $setup;
            if (!empty($data)) {
                //echo $data;
                $ret = post('http://localhost:9200/_bulk',$data);
                echo $start;
            }
            $data = '';
        }else{
            die('没数据');
        }
    }
    
    
}

function post($url, $post_data = '', $timeout = 5){//curl  
    $ch = curl_init();  
    curl_setopt ($ch, CURLOPT_URL, $url);
    $header = array();
    $header[] = 'Content-Type:application/json;charset=utf-8';
    curl_setopt ($ch,CURLOPT_HTTPHEADER,$header);
    curl_setopt ($ch, CURLOPT_POST, 1);  
    if($post_data != ''){  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);  
    }  
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);   
    curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);  
    curl_setopt($ch, CURLOPT_HEADER, false);  
    $file_contents = curl_exec($ch);  
    curl_close($ch);  
    return $file_contents;  
}  

