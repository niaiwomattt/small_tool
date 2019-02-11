<?php

class C {

    public function __call($name, $params)
      {
            // $this->diy();
            var_dump($name, $params);exit;
      }
}

$o = new C();
$o->pan('1',['2']);