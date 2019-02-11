#!/bin/sh

set -eo pipefail

for i in $(find -type f -name "*Test.php" | xargs -I {} basename {} .php)
do
    echo $i."php"
    D:/phpStudy/php/php-7.0.12-nts/phpunit.cmd --configuration phpunit.xml  $i."php"
done