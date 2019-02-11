<?php

$str = './鏂囦欢1.zip';
$file = iconv('GBK','UTF-8',$str);

file_put_contents($file,'123');