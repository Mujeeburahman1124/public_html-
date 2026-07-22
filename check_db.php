<?php
require 'config2.php';
$res = db()->query("SHOW TABLES");
if ($res) {
    while($row = $res->fetch_array()) {
        echo $row[0] . "\n";
    }
} else {
    echo "Query failed: " . db()->error . "\n";
}
unlink(__FILE__);
