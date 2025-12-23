<?php
$db = new PDO('sqlite:./data/app.db');
$result = $db->query("PRAGMA table_info(tickets);");
$columns = $result->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo $col['name'] . ' (' . $col['type'] . ')' . PHP_EOL;
}
