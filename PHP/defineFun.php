<?php

function say($str)
{
    echo $str;
}

define('HAHA',"say(123);");


echo HAHA();