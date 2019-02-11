<?php
class config
{
    private $conf = array();
    public $data = '';

    public function __get($key)
    {
        $this->conf[$key];
        return $this;
    }

    public function __set($key, $value)
    {
        $this->conf[$key] = $value;
        return $this;
    }

    public function __call($methodname, $arg)
    {
        $conf = $this->conf[$methodname];
        $conf();
        return $this;
    }

}

$conf = new config();
$conf->get_age = function () {
    echo "aaa";
};

$conf->get_sex = function () {
    echo "bbb";
};

$conf->get_age()->get_sex();
