<?php
class mydb {

    public $conn = null;
    private $config = [];

    public function __construct()
    {
        echo "start\n";
        $this->config = include("./dbconfig.php");
        $this->conn = new mysqli($this->config['host'],$this->config['user'],$this->config['pwd'],$this->config['dbname']);
        if ($this->conn->connect_error) {
            die("连接失败: \n" . $this->conn->connect_error);
        }
        return $this;  
    }

    public function __destruct()
    {
        $this->conn->close();
        echo "end\n";
    }

}
