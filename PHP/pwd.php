<?php
$slat = 'L0KKDtnDD3zDv6MO';
$dbpwd = '$2y$10$JEncEj3AJVL0QWJCv4Bv7Og2BwJk79jXbYQOoqXnCA4yBEIy5..wK';
$pwd = 'chenshuqi';
$in = password_hash($slat. $pwd, PASSWORD_DEFAULT);
$s = password_verify($slat . $pwd, $dbpwd);

echo $in,"\n";

if ($s) {
    echo 'true';
}else {
    echo 'false';
}