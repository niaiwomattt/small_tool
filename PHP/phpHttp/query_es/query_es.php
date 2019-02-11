<?php
require __DIR__."/autoLoad.php";

use \Vendor\Parse as Parse;

date_default_timezone_set('PRC');
$r = new Parse\Request();
$r->getDataClient(new Parse\Parse());
$r->sendDataServe(new Parse\Curl(),'http://192.168.1.21:9200/samples_v1/_search', 'elastic', 'QmXzT5BXU*iE+=p-?NGn');
$r->formartReData();