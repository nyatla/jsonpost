<?php
// // SQLiteデータベースに接続
$pdo = new PDO('sqlite:test.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE IF NOT EXISTS test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, value TEXT NOT NULL)");
$stmt = $pdo->prepare("INSERT INTO test (name, value) VALUES (?, ?)");
$sql = "INSERT INTO test (name, value) VALUES (:name, :value)";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':name',"name", SQLITE3_TEXT);
$stmt->bindValue(':value', "value", SQLITE3_TEXT);
$stmt->execute();

print("全行選択");
$row = $pdo->query("SELECT * FROM test")->fetch(PDO::FETCH_ASSOC);
print_r($row);

print("id選択");
$row = $pdo->query("SELECT * FROM test WHERE id=1")->fetch(PDO::FETCH_ASSOC);
print_r($row);


print("name選択");
$row = $pdo->query("SELECT * FROM test WHERE name='name'")->fetch(PDO::FETCH_ASSOC);
print_r($row);


print_r('おわり🌱');
