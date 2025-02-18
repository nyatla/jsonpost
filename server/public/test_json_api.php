<?php
try {
    // SQLiteデータベースに接続（メモリ上に一時DBを作成）
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // SQLクエリ
    $sql = "WITH
  functions(name) AS (
    SELECT name FROM pragma_function_list()
    UNION ALL
    SELECT name FROM pragma_module_list() GROUP BY name
  ),
  json_functions(name) AS (
    SELECT name FROM functions WHERE name = 'json' OR name LIKE 'json$_%' ESCAPE '$'
  ),
  jsonb_functions(name) AS (
    SELECT name FROM functions WHERE name = 'jsonb' OR name LIKE 'jsonb$_%' ESCAPE '$'
  )
SELECT
       ROW_NUMBER() OVER (ORDER BY json.name) AS No,
       json.name AS json_functions,
       jsonb.name AS jsonb_functions
  FROM json_functions json
  LEFT JOIN jsonb_functions jsonb
    ON jsonb.name = (
      CASE
        WHEN instr(json.name, '_') > 0
        THEN substr(json.name, 1, instr(json.name, '_') - 1) || 'b' || substr(json.name, instr(json.name, '_'))
        ELSE json.name || 'b'
      END
    )
 GROUP BY json.name
 ORDER BY json.name;";

    // クエリを実行
    $stmt = $pdo->query($sql);
    
    // 結果を取得して表示
    echo "<table border='1'>";
    echo "<tr><th>No</th><th>JSON Functions</th><th>JSONB Functions</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>{$row['No']}</td>";
        echo "<td>{$row['json_functions']}</td>";
        echo "<td>" . ($row['jsonb_functions'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
