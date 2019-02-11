<?php
require("./dblink.php");

$db = new mydb();
$start = 0;
$end = 10;
$result = $db->conn->query("select * from sampinfos where id>{$start} and id <{$end};");
if ($result->num_rows > 0) {
    // 输出数据
    while($row = $result->fetch_assoc()) {
        $data .= json_encode($row) . "\n" ;
        //echo "id: " . $row["id"]. " - Name: " . $row["firstname"]. " " . $row["lastname"]. "<br>";
    }
    echo $data;
    $data = '';
}else{
    die('没数据');
}
$db = null;