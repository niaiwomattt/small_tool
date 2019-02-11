<?php
date_default_timezone_set('UTC');
$btime = '2018-05-10';
$bstr  = date('Y-m-dTH:i:sZ',strtotime($btime) );
echo $bstr,"\n";
$ztime = '2018-05-09T04:29:34.000Z';
$str = date("Y-m-d H:i:s",strtotime($ztime) );
echo $str;

// ISO8601 标准格式，Logstash 使用的格式
echo date(DateTime::ATOM, strtotime($btime));