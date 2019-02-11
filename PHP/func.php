<?php

function app($fun = __FUNCTION__)
{
    echo $fun;
}

function aaa()
{

    app(__FUNCTION__);
}

aaa();