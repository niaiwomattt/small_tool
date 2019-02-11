<?php

function redexIndex($arr,$clumn)
{
    return array_combine(array_column($arr,$clumn),$arr) ;
}

$arr = [
    [
        'id'=>6,
        'name'=>'a',
        'sex'=>1,
    ],
    [
        'id'=>7,
        'name'=>'b',
        'sex'=>2,
    ],
    [
        'id'=>8,
        'name'=>'c',
        'sex'=>3,
    ],
];

$rarr = redexIndex($arr,'id');
var_dump($rarr);