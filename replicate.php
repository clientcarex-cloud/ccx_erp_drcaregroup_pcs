<?php
declare(strict_types=1);

ini_set('max_execution_time', '0');
ini_set('memory_limit', '-1');

/**
 * Load configuration values from .env, $_ENV, $_SERVER, and getenv().
 */
function loadEnv(string $path): array
{
    $data = [];

    if (is_readable($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                if (!is_string($line)) {
                    continue;
                }
                $trimmed = trim($line);
                if ($trimmed === '' || strpos($trimmed, '#') === 0) {
                    continue;
                }
                $parts = explode('=', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                    $value = substr($value, 1, -1);
                }
                $data[$key] = $value;
            }
        }
    }

    $merged = array_merge($data, $_ENV, $_SERVER);
    foreach ($merged as $key => $value) {
        $envValue = getenv($key);
        if ($envValue !== false) {
            $merged[$key] = $envValue;
        }
    }

    return $merged;
}

/**
 * Resolve database configuration for the given role.
 *
 * @return array{host:string,port:?string,name:string,user:string,pass:string,charset:?string,collation:?string}
 */
function getDbConfig(array $env, string $role): array
{
    $keys = [
        'source' => [
            'host' => ['REPL_SOURCE_DB_HOST', 'REPL_SOURCE_DB_HOSTNAME', 'SOURCE_DB_HOST', 'SOURCE_DB_HOSTNAME', 'APP_SOURCE_DB_HOST', 'APP_SOURCE_DB_HOSTNAME', 'APP_DB_HOST', 'APP_DB_HOSTNAME', 'DB_HOST', 'DB_HOSTNAME'],
            'port' => ['REPL_SOURCE_DB_PORT', 'SOURCE_DB_PORT', 'APP_SOURCE_DB_PORT', 'APP_DB_PORT', 'DB_PORT'],
            'name' => ['REPL_SOURCE_DB_NAME', 'REPL_SOURCE_DB_DATABASE', 'SOURCE_DB_NAME', 'SOURCE_DB_DATABASE', 'APP_SOURCE_DB_NAME', 'APP_DB_NAME', 'DB_NAME', 'DB_DATABASE'],
            'user' => ['REPL_SOURCE_DB_USER', 'REPL_SOURCE_DB_USERNAME', 'SOURCE_DB_USER', 'SOURCE_DB_USERNAME', 'APP_SOURCE_DB_USER', 'APP_SOURCE_DB_USERNAME', 'APP_DB_USER', 'APP_DB_USERNAME', 'DB_USER', 'DB_USERNAME'],
            'pass' => ['REPL_SOURCE_DB_PASS', 'REPL_SOURCE_DB_PASSWORD', 'SOURCE_DB_PASS', 'SOURCE_DB_PASSWORD', 'APP_SOURCE_DB_PASS', 'APP_SOURCE_DB_PASSWORD', 'APP_DB_PASS', 'APP_DB_PASSWORD', 'DB_PASS', 'DB_PASSWORD'],
            'charset' => ['REPL_SOURCE_DB_CHARSET', 'SOURCE_DB_CHARSET', 'APP_SOURCE_DB_CHARSET', 'APP_DB_CHARSET', 'DB_CHARSET'],
            'collation' => ['REPL_SOURCE_DB_COLLATION', 'SOURCE_DB_COLLATION', 'APP_SOURCE_DB_COLLATION', 'APP_DB_COLLATION', 'DB_COLLATION'],
        ],
        'target' => [
            'host' => ['REPL_TARGET_DB_HOST', 'REPL_TARGET_DB_HOSTNAME', 'TARGET_DB_HOST', 'TARGET_DB_HOSTNAME', 'DEST_DB_HOST', 'DEST_DB_HOSTNAME', 'SLAVE_DB_HOST', 'SLAVE_DB_HOSTNAME', 'REPLICA_DB_HOST', 'REPLICA_DB_HOSTNAME', 'SECONDARY_DB_HOST', 'SECONDARY_DB_HOSTNAME', 'APP_REPLICA_DB_HOST', 'APP_REPLICA_DB_HOSTNAME', 'APP_SLAVE_DB_HOST', 'APP_SLAVE_DB_HOSTNAME'],
            'port' => ['REPL_TARGET_DB_PORT', 'TARGET_DB_PORT', 'DEST_DB_PORT', 'SLAVE_DB_PORT', 'REPLICA_DB_PORT', 'SECONDARY_DB_PORT', 'APP_REPLICA_DB_PORT', 'APP_SLAVE_DB_PORT'],
            'name' => ['REPL_TARGET_DB_NAME', 'REPL_TARGET_DB_DATABASE', 'TARGET_DB_NAME', 'TARGET_DB_DATABASE', 'DEST_DB_NAME', 'DEST_DB_DATABASE', 'SLAVE_DB_NAME', 'SLAVE_DB_DATABASE', 'REPLICA_DB_NAME', 'REPLICA_DB_DATABASE', 'SECONDARY_DB_NAME', 'SECONDARY_DB_DATABASE', 'APP_REPLICA_DB_NAME', 'APP_SLAVE_DB_NAME'],
            'user' => ['REPL_TARGET_DB_USER', 'REPL_TARGET_DB_USERNAME', 'TARGET_DB_USER', 'TARGET_DB_USERNAME', 'DEST_DB_USER', 'DEST_DB_USERNAME', 'SLAVE_DB_USER', 'SLAVE_DB_USERNAME', 'REPLICA_DB_USER', 'REPLICA_DB_USERNAME', 'SECONDARY_DB_USER', 'SECONDARY_DB_USERNAME', 'APP_REPLICA_DB_USER', 'APP_REPLICA_DB_USERNAME', 'APP_SLAVE_DB_USER', 'APP_SLAVE_DB_USERNAME'],
            'pass' => ['REPL_TARGET_DB_PASS', 'REPL_TARGET_DB_PASSWORD', 'TARGET_DB_PASS', 'TARGET_DB_PASSWORD', 'DEST_DB_PASS', 'DEST_DB_PASSWORD', 'SLAVE_DB_PASS', 'SLAVE_DB_PASSWORD', 'REPLICA_DB_PASS', 'REPLICA_DB_PASSWORD', 'SECONDARY_DB_PASS', 'SECONDARY_DB_PASSWORD', 'APP_REPLICA_DB_PASS', 'APP_REPLICA_DB_PASSWORD', 'APP_SLAVE_DB_PASS', 'APP_SLAVE_DB_PASSWORD'],
            'charset' => ['REPL_TARGET_DB_CHARSET', 'TARGET_DB_CHARSET', 'DEST_DB_CHARSET', 'SLAVE_DB_CHARSET', 'REPLICA_DB_CHARSET', 'SECONDARY_DB_CHARSET', 'APP_REPLICA_DB_CHARSET', 'APP_SLAVE_DB_CHARSET'],
            'collation' => ['REPL_TARGET_DB_COLLATION', 'TARGET_DB_COLLATION', 'DEST_DB_COLLATION', 'SLAVE_DB_COLLATION', 'REPLICA_DB_COLLATION', 'SECONDARY_DB_COLLATION', 'APP_REPLICA_DB_COLLATION', 'APP_SLAVE_DB_COLLATION'],
        ],
    ];

    if (!isset($keys[$role])) {
        throw new InvalidArgumentException('Unknown database role: ' . $role);
    }

    $conf = $keys[$role];

    $host = firstEnvValue($env, $conf['host'], true, $role, 'host');
    $dbName = firstEnvValue($env, $conf['name'], true, $role, 'database');
    $user = firstEnvValue($env, $conf['user'], true, $role, 'username');
    $pass = firstEnvValue($env, $conf['pass'], false, $role, 'password', true);
    $port = firstEnvValue($env, $conf['port'], false, $role, 'port');
    $charset = firstEnvValue($env, $conf['charset'], false, $role, 'charset');
    $collation = firstEnvValue($env, $conf['collation'], false, $role, 'collation');

    return [
        'host' => $host,
        'port' => $port,
        'name' => $dbName,
        'user' => $user,
        'pass' => $pass ?? '',
        'charset' => $charset ?: 'utf8mb4',
        'collation' => $collation ?: null,
    ];
}

/**
 * Pick the first non-empty environment value.
 */
function firstEnvValue(array $env, array $candidates, bool $required, string $role, string $label, bool $allowEmpty = false): ?string
{
    foreach ($candidates as $candidate) {
        if (array_key_exists($candidate, $env)) {
            $value = $env[$candidate];
            if (is_string($value)) {
                $trimmed = trim($value);
            } else {
                $trimmed = (string) $value;
            }

            if ($trimmed === '' && !$allowEmpty) {
                continue;
            }

            return $value === null ? null : (string) $value;
        }

        $envValue = getenv($candidate);
        if ($envValue !== false) {
            $trimmed = trim($envValue);
            if ($trimmed === '' && !$allowEmpty) {
                continue;
            }
            return (string) $envValue;
        }
    }

    if ($required) {
        throw new RuntimeException(
            sprintf(
                'Missing %s database %s configuration. Please set one of: %s',
                $role,
                $label,
                implode(', ', $candidates)
            )
        );
    }

    return null;
}

/**
 * Create a PDO connection using the provided configuration.
 *
 * @param array{host:string,port:?string,name:string,user:string,pass:string,charset:?string,collation:?string} $config
 */
function createConnection(array $config): PDO
{
    $dsnParts = ['host=' . $config['host'], 'dbname=' . $config['name']];
    if (!empty($config['port'])) {
        $dsnParts[] = 'port=' . $config['port'];
    }
    if (!empty($config['charset'])) {
        $dsnParts[] = 'charset=' . $config['charset'];
    }
    $dsn = 'mysql:' . implode(';', $dsnParts);

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (!empty($config['charset']) && defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES ' . $config['charset'];
    }

    $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);

    if (!empty($config['collation'])) {
        $charset = $config['charset'] ?: 'utf8mb4';
        $pdo->exec(sprintf('SET NAMES %s COLLATE %s', $pdo->quote($charset), $pdo->quote($config['collation'])));
    }

    return $pdo;
}

/**
 * Replicate all base tables from source to target.
 *
 * @return array<int, array{table:string,status:string,processed_rows:int,batches:int,mode:string,duration_ms:int,message?:string}>
 */
function replicateDatabase(PDO $source, PDO $target, bool $fullSync, string $sourceDbName, int $batchSize = 500): array
{
    $report = [];
    $columnKey = 'Tables_in_' . $sourceDbName;

    $target->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        $tablesResult = $source->query('SHOW FULL TABLES');
        if ($tablesResult === false) {
            throw new RuntimeException('Could not list tables from source database.');
        }

        while ($row = $tablesResult->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row) || !isset($row[$columnKey])) {
                continue;
            }
            $tableName = (string) $row[$columnKey];
            $tableType = isset($row['Table_type']) ? strtoupper((string) $row['Table_type']) : 'BASE TABLE';
            if ($tableType !== 'BASE TABLE') {
                continue;
            }

            try {
                $result = replicateTable($source, $target, $tableName, $fullSync, $batchSize);
                $result['table'] = $tableName;
                $report[] = $result;
            } catch (Throwable $tableException) {
                $message = formatException($tableException);
                error_log(sprintf('[replicate.php] Replication failed for table %s: %s', $tableName, $message));
                $report[] = [
                    'table' => $tableName,
                    'status' => 'error',
                    'processed_rows' => 0,
                    'batches' => 0,
                    'mode' => $fullSync ? 'full' : 'incremental',
                    'duration_ms' => 0,
                    'message' => $message,
                ];
            }
        }
    } finally {
        $target->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    return $report;
}

/**
 * Replicate a single table.
 *
 * @return array{status:string,processed_rows:int,batches:int,mode:string,duration_ms:int}
 */
function replicateTable(PDO $source, PDO $target, string $table, bool $fullSync, int $batchSize): array
{
    $start = microtime(true);

    ensureTableExists($source, $target, $table);

    $columnStmt = $source->query('SHOW COLUMNS FROM ' . quoteIdentifier($table));
    if ($columnStmt === false) {
        throw new RuntimeException('Unable to read column metadata for table ' . $table);
    }

    $columns = [];
    while ($column = $columnStmt->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($column['Field'])) {
            continue;
        }
        $extra = isset($column['Extra']) ? strtoupper((string) $column['Extra']) : '';
        if (strpos($extra, 'GENERATED') !== false) {
            continue;
        }
        $columns[] = $column['Field'];
    }

    if (empty($columns)) {
        return [
            'status' => 'success',
            'processed_rows' => 0,
            'batches' => 0,
            'mode' => $fullSync ? 'full' : 'incremental',
            'duration_ms' => (int) ((microtime(true) - $start) * 1000),
        ];
    }

    if ($fullSync) {
        $target->exec('TRUNCATE TABLE ' . quoteIdentifier($table));
    }

    $columnList = implode(', ', array_map('quoteIdentifier', $columns));
    $valuesTemplate = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

    $updateSql = '';
    if (!$fullSync) {
        $updates = [];
        foreach ($columns as $column) {
            $identifier = quoteIdentifier($column);
            $updates[] = sprintf('%s = VALUES(%s)', $identifier, $identifier);
        }
        $updateSql = ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
    }

    $processed = 0;
    $batches = 0;
    $offset = 0;

    $beganTransaction = false;
    if (!$target->inTransaction()) {
        $target->beginTransaction();
        $beganTransaction = true;
    }

    try {
        while (true) {
            $selectStmt = $source->prepare('SELECT * FROM ' . quoteIdentifier($table) . ' LIMIT :limit OFFSET :offset');
            $selectStmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
            $selectStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $selectStmt->execute();

            $rows = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) {
                break;
            }

            $chunks = [];
            $params = [];
            foreach ($rows as $row) {
                $chunks[] = $valuesTemplate;
                foreach ($columns as $column) {
                    $params[] = array_key_exists($column, $row) ? $row[$column] : null;
                }
            }

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES %s%s',
                quoteIdentifier($table),
                $columnList,
                implode(', ', $chunks),
                $updateSql
            );

            $insertStmt = $target->prepare($sql);
            $insertStmt->execute($params);

            $offset += count($rows);
            $processed += count($rows);
            $batches++;
        }

        if ($beganTransaction) {
            $target->commit();
        }
    } catch (Throwable $exception) {
        if ($beganTransaction && $target->inTransaction()) {
            $target->rollBack();
        }
        throw $exception;
    }

    return [
        'status' => 'success',
        'processed_rows' => $processed,
        'batches' => $batches,
        'mode' => $fullSync ? 'full' : 'incremental',
        'duration_ms' => (int) ((microtime(true) - $start) * 1000),
    ];
}

/**
 * Ensure the table exists on the target database with the same definition as the source.
 */
function ensureTableExists(PDO $source, PDO $target, string $table): void
{
    if (tableExists($target, $table)) {
        return;
    }

    $createStmt = $source->query('SHOW CREATE TABLE ' . quoteIdentifier($table));
    if ($createStmt === false) {
        throw new RuntimeException('Unable to read table definition for ' . $table);
    }

    $definition = $createStmt->fetch(PDO::FETCH_ASSOC);
    if ($definition === false || !isset($definition['Create Table'])) {
        throw new RuntimeException('Unexpected response when reading table definition for ' . $table);
    }

    $createSql = $definition['Create Table'];
    $target->exec($createSql);
}

/**
 * Determine whether a table already exists on the provided connection.
 */
function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE :table');
    $stmt->execute([':table' => $table]);
    return $stmt->fetchColumn() !== false;
}

/**
 * Quote an identifier (table, column) for use in SQL statements.
 */
function quoteIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function formatException(Throwable $exception): string
{
    $parts = [];
    $message = trim($exception->getMessage());
    if ($message !== '') {
        $parts[] = $message;
    }

    if ($exception instanceof PDOException) {
        $info = $exception->errorInfo ?? null;
        if (is_array($info) && !empty($info)) {
            $parts[] = 'errorInfo=' . json_encode($info);
        }
    }

    $code = $exception->getCode();
    if ($code !== 0 && $code !== '' && $code !== null) {
        $parts[] = 'code=' . (string) $code;
    }

    if (empty($parts)) {
        $parts[] = get_class($exception);
    }

    return implode(' | ', $parts);
}

$env = loadEnv(__DIR__ . DIRECTORY_SEPARATOR . '.env');
$mode = isset($_POST['mode']) && in_array($_POST['mode'], ['full', 'incremental'], true) ? $_POST['mode'] : '';

$status = null;
$message = '';
$report = [];
$durationMs = null;

if ($mode !== '') {
    $started = microtime(true);
    try {
        $sourceConfig = getDbConfig($env, 'source');
        $targetConfig = getDbConfig($env, 'target');

        if (
            $sourceConfig['host'] === $targetConfig['host'] &&
            $sourceConfig['name'] === $targetConfig['name'] &&
            $sourceConfig['user'] === $targetConfig['user']
        ) {
            throw new RuntimeException('Source and target database configurations are identical. Adjust .env to point target to the replica database.');
        }

        $source = createConnection($sourceConfig);
        $target = createConnection($targetConfig);

        $report = replicateDatabase($source, $target, $mode === 'full', $sourceConfig['name']);

        $status = 'success';
        $durationMs = (int) ((microtime(true) - $started) * 1000);
        $message = sprintf(
            'Replication (%s sync) completed for %d table(s).',
            $mode,
            count($report)
        );
    } catch (Throwable $exception) {
        $status = 'error';
        $message = $exception->getMessage();
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (PHP_SAPI === 'cli') {
    if ($status !== null) {
        $output = [
            'status' => $status,
            'message' => $message,
            'mode' => $mode,
            'duration_ms' => $durationMs,
            'report' => $report,
        ];
        fwrite(STDOUT, json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL);
    }
    return;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Manual Database Replication</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f6fa; margin: 0; padding: 20px; }
        .container { max-width: 960px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        h1 { margin-top: 0; font-size: 24px; }
        form { margin-bottom: 24px; }
        button { font-size: 16px; padding: 12px 20px; margin-right: 12px; border: none; border-radius: 4px; cursor: pointer; }
        button[type="submit"][value="incremental"] { background: #0069d9; color: #fff; }
        button[type="submit"][value="full"] { background: #dc3545; color: #fff; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 14px; }
        th, td { border: 1px solid #dcdcdc; padding: 8px; text-align: left; }
        th { background: #f1f1f1; }
        .status-success { color: #1e7e34; font-weight: bold; }
        .status-error { color: #bd2130; font-weight: bold; }
        .notes { font-size: 13px; color: #444; margin-top: 24px; line-height: 1.5; }
        .duration { font-size: 14px; color: #555; margin-top: -8px; margin-bottom: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manual Database Replication</h1>
        <p>Use the buttons below to synchronise data from the source database to the replica defined in your <code>.env</code> file.</p>
        <form method="post">
            <button type="submit" name="mode" value="incremental">Incremental Sync</button>
            <button type="submit" name="mode" value="full">Full Sync</button>
        </form>
        <?php if ($status === 'success'): ?>
            <div class="alert alert-success"><?php echo h($message); ?></div>
            <?php if ($durationMs !== null): ?>
                <div class="duration">Elapsed time: <?php echo h(number_format($durationMs)); ?> ms</div>
            <?php endif; ?>
        <?php elseif ($status === 'error'): ?>
            <div class="alert alert-error"><?php echo h($message); ?></div>
        <?php endif; ?>

        <?php if (!empty($report)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Status</th>
                        <th>Rows Processed</th>
                        <th>Batches</th>
                        <th>Mode</th>
                        <th>Duration (ms)</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report as $row): ?>
                        <tr>
                            <td><?php echo h($row['table']); ?></td>
                            <td class="<?php echo h('status-' . $row['status']); ?>"><?php echo h(ucfirst($row['status'])); ?></td>
                            <td><?php echo h(number_format((int) $row['processed_rows'])); ?></td>
                            <td><?php echo h((int) $row['batches']); ?></td>
                            <td><?php echo h($row['mode']); ?></td>
                            <td><?php echo h(number_format((int) $row['duration_ms'])); ?></td>
                            <td><?php echo isset($row['message']) ? h($row['message']) : ''; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="notes">
            <strong>Configuration tips:</strong>
            <ul>
                <li>Source credentials can be defined with keys such as <code>REPL_SOURCE_DB_HOSTNAME</code> or fallback to <code>APP_DB_HOSTNAME</code>.</li>
                <li>Target credentials should be supplied with keys like <code>REPL_TARGET_DB_HOSTNAME</code>, <code>DEST_DB_NAME</code>, and <code>DEST_DB_USERNAME</code>.</li>
                <li>Incremental sync keeps destination rows in place and overwrites rows with matching primary keys. Full sync truncates each table before copying.</li>
            </ul>
        </div>
    </div>
</body>
</html>
