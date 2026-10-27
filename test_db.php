<?php
$pdo = new PDO('mysql:host=localhost;dbname=sidge', 'root', '');
$stmt = $pdo->query("SHOW COLUMNS FROM formacion_academica");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r(array_column($cols, 'Field'));
