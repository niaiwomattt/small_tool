<?php

echo base64_encode(openssl_random_pseudo_bytes(32));
echo "\n";
echo uuiqid();