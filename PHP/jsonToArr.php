<?php

$json = file_get_contents('./clients.json');
$data = json_decode($json, true);
$post = urlencode($)