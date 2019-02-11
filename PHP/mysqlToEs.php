<?php
/**
 * 执行 php -S 0.0.0.0:8686 mysqlToes.php 
 * 然后访问 localhost:8686?table=tablename
 * 查看源代码，得到格式化的es的json mapping 结构。
 */
class mysqlToEs {

    private $conn = null;
    private $data = [];
    private $es_type = '';

    /* 数据库处理部分 */
    public function connectDb( $host = '', $dbname = '', $user = '', $pwd = '') {
        $this->conn = new mysqli($host,$user,$pwd,$dbname);
        if ($this->conn->connect_error) {
            die("连接失败: " . $this->conn->connect_error);
        }
        return $this;
    }
    // 关闭数据库
    public function closeDb(Type $var = null)
    {
        $this->conn->close();
    }

    // 获取表结构
    public function getTableDesc($table = '') {
        $sql = "desc sampinfos;";
        $result = $this->conn->query($sql);

        if ($result->num_rows > 0) {
            // 输出数据
            while($row = $result->fetch_assoc()) {
                $this->data[] = $row;
                //echo "id: " . $row["id"]. " - Name: " . $row["firstname"]. " " . $row["lastname"]. "<br>";
            }
        }
        return $this;
    }

    /** 转换部分 **/
    function myTOes() {
        // 支持的转换类型
        $types = [
            "char"    => ['type'=> 'keyword'], // char 放到 varchar上面，要不然char的结果会覆盖varchar的结果
            "varchar" => 
                [
                    'type'=>'text',
                    "analyzer"=>"ngram-4",
                    "search_analyzer"=>"ngram-4",
                    "fields" => [
                        "raw" => [
                            "type" => "keyword"
                        ]
                    ]
                ]
            ,
            "int"     => ['type'=> 'integer'],
            "tinyint" => ["type"=> "short"],
            "datetime" => [
                "type"=> "date",
                "format"=> "yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||epoch_millis"
            ]
        ];
        /**
         * value 是个key value数组
         * Field=> id
         * Type=> int(11)
         * Null=> No
         * 用 Type 匹配 $types 的key
         */
        foreach ($this->data as $value) {
            $typeTag = $this->getTypeTag($value['Type']);
            if ( isset($types[$typeTag][0]) ) {
                foreach ($types[$typeTag] as $k => $v) {
                    if (is_numeric($k) ) {
                        $this->es_type[$value['Field']] = $v;                        
                    }else {
                        $filed = $value['Field'].$k;
                        $this->es_type[$filed] = $v;                        
                    }
                }
            }else {
                    $this->es_type[$value['Field']] = $types[$typeTag];                
            }
            // foreach ($types as $k => $v) {
            //     if (stripos($value['Type'],$k) === false) {
            //         continue;
            //     }else{
            //         $this->es_type[$value['Field']] = $v;
            //     }
            // }
            
        }
        $tmpPro = [];
        $tmpPro['properties'] = $this->es_type;
        $this->es_type = $tmpPro; 
        return $this;
    }

    // 获取字段的数据类型
    public function getTypeTag($typeStr) {
        $strLen = stripos($typeStr,'(');
        if (!$strLen) {
            $strLen = strlen($typeStr);
        }
        $typeTag = substr( $typeStr,0, $strLen);
        return $typeTag;
    }

    public function notAssocArray(array $var) {  
        return array_diff_assoc(array_keys($var), range(0, sizeof($var))) ? TRUE : FALSE;  
    } 

    // 输出es结构数据
    public function putEsType() {
        $this->closeDb();              
        header('Content-type: application/json');
        echo json_encode($this->es_type,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }

    // 输出html代码
    public function putHtml() {
            return <<<P
            <!DOCTYPE html>
            <html>
            <head>
                <title>MySQL表结构转换为ElasticSearch的 mapping</title>
            </head>
            <body>
                <center>
                    <h1>MySQL表结构转换为ElasticSearch的 mapping</h1>
                    <form action="" method="post">
                    <lable>Host：</lable><input type="text" name="host" value="192.168.1.20" />   <br/>
                    <lable>DBname：</lable><input type="text" name="dbname" value="Samples" />  <br/>
                    <lable>User：</lable><input type="text" name="user" value="sampr" />  <br/>
                    <lable>Pwd：</lable><input type="text" name="pwd" value="sampR123#" />  <br/>
                    <lable>Table：</lable><input type="text" name="table" value="sampinfos" />  <br/>
                    <input type="submit" height="20px" width="50px" value="转换">
                    </form>
                </center>
            </body>
            </html>
P;
// p的位置不能动
    }
}

$new = new \mysqlToEs();

if ($_POST) {
    $new->connectDb( $_POST['host'], $_POST['dbname'], $_POST['user'], $_POST['pwd'])->getTableDesc($_POST['table'])->myTOes()->putEsType();
}else {
    echo $new->putHtml();
}

