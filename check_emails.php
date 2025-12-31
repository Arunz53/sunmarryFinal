<?php
require_once 'db.php';
$s = getDB()->prepare('SELECT id, username, email FROM users WHERE id IN (1,15)');
$s->execute();
foreach ($s->fetchAll() as $u) {
    echo 'ID: '.$u['id'].' | User: '.$u['username'].' | Email: '.($u['email'] ?? 'NULL').PHP_EOL;
}
?>
