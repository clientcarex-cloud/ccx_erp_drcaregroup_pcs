<?php
declare(strict_types=1);

require_once __DIR__ . '/replicate.php';

$env = loadEnv(__DIR__ . DIRECTORY_SEPARATOR . '.env');
$sourceConfig = getDbConfig($env, 'source');
$source = createConnection($sourceConfig);

$table = $argv[1] ?? '';
if ($table === '') {
    fwrite(STDERR, "Usage: php inspect_table.php <table_name>\n");
    exit(1);
}

$stmt = $source->query('SHOW CREATE TABLE ' . quoteIdentifier($table));
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row === false) {
    fwrite(STDERR, "Table not found or SHOW CREATE TABLE failed.\n");
    exit(1);
}

echo $row['Create Table'] ?? json_encode($row, JSON_PRETTY_PRINT);
echo PHP_EOL;
