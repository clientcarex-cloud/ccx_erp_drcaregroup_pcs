<?php
/**
 * Simple database replication utility.
 *
 * Usage:
 *   php replicate_database.php
 *
 * Web UI:
 *   Open replicate_database.php in your browser to access a simple control
 *   panel with a button that streams real-time logs while the copy runs.
 *   Progress is tracked in replicate_state.json so retries resume where they
 *   left off. Delete that file to force a full rebuild.
 *
 * The script expects the following variables to be available either in the environment
 * or in the local .env file located next to this script:
 *
 *   APP_DB_HOSTNAME
 *   APP_DB_USERNAME
 *   APP_DB_PASSWORD
 *   APP_DB_NAME
 *   APP_DB_CHARSET      (optional, defaults to utf8mb4)
 *   APP_DB_COLLATION    (optional, defaults to utf8mb4_unicode_ci)
 *
 *   REPL_TARGET_DB_HOSTNAME
 *   REPL_TARGET_DB_USERNAME
 *   REPL_TARGET_DB_PASSWORD
 *   REPL_TARGET_DB_NAME
 *   REPL_TARGET_DB_CHARSET   (optional, defaults to utf8mb4)
 *   REPL_TARGET_DB_COLLATION (optional, defaults to utf8mb4_unicode_ci)
 *
 * A full copy from the source database into the target database will be performed.
 * All tables, views, and triggers currently present in the target database will be
 * dropped/replaced.
 */

declare(strict_types=1);

ini_set('memory_limit', '-1');
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(0);

if (!defined('STDOUT')) {
    $stdout = fopen('php://output', 'w');
    if ($stdout === false) {
        throw new RuntimeException('Unable to open stdout stream.');
    }
    define('STDOUT', $stdout);
}

bootstrap(__DIR__);

/**
 * Application entry point.
 */
function main(string $rootDir): void
{
    loadEnvFile($rootDir . DIRECTORY_SEPARATOR . '.env');

    $source = buildConnectionConfig('APP_DB_');
    $target = buildConnectionConfig('REPL_TARGET_DB_');

    validateDistinctDatabases($source, $target);

    $sourcePdo = createPdoConnection($source);
    $targetPdo = createPdoConnection($target);

    $progress = new ReplicationProgress($rootDir . DIRECTORY_SEPARATOR . 'replicate_state.json');

    $copier = new DatabaseCopier(
        $sourcePdo,
        $targetPdo,
        $progress,
        $source['name'],
        $target['name']
    );
    $copier->run();

    $progress->clear();

    emit_log();
    emit_log('Database copy completed successfully.');
}

/**
 * Reads a .env file and populates the current process environment.
 */
function loadEnvFile(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new RuntimeException("Unable to read .env file at {$path}");
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        [$key, $value] = $parts;
        $key = trim($key);
        $value = trim($value);

        if ($value === '') {
            $parsedValue = '';
        } else {
            $value = trim($value);
            if (
                (starts_with($value, '"') && ends_with($value, '"')) ||
                (starts_with($value, "'") && ends_with($value, "'"))
            ) {
                $parsedValue = substr($value, 1, -1);
            } else {
                $parsedValue = $value;
            }
        }

        putenv("{$key}={$parsedValue}");
        $_ENV[$key] = $parsedValue;
        $_SERVER[$key] = $parsedValue;
    }
}

/**
 * Builds a DB config array by reading prefixed environment variables.
 *
 * @return array{
 *     host: string,
 *     username: string,
 *     password: string,
 *     name: string,
 *     charset: string,
 *     collation: string
 * }
 */
function buildConnectionConfig(string $prefix): array
{
    $required = [
        'host' => $prefix . 'HOSTNAME',
        'username' => $prefix . 'USERNAME',
        'password' => $prefix . 'PASSWORD',
        'name' => $prefix . 'NAME',
    ];

    $config = [];
    foreach ($required as $key => $envKey) {
        $value = getenv($envKey);
        if ($value === false || $value === '') {
            throw new InvalidArgumentException("Missing required environment variable: {$envKey}");
        }
        $config[$key] = $value;
    }

    $config['charset'] = getenv($prefix . 'CHARSET') ?: 'utf8mb4';
    $config['collation'] = getenv($prefix . 'COLLATION') ?: 'utf8mb4_unicode_ci';

    return $config;
}

/**
 * Ensures the source and target databases are not the same.
 */
function validateDistinctDatabases(array $source, array $target): void
{
    if (
        $source['host'] === $target['host'] &&
        $source['name'] === $target['name'] &&
        $source['username'] === $target['username']
    ) {
        throw new InvalidArgumentException('Source and target database appear to be identical.');
    }
}

/**
 * Creates a PDO connection using the provided configuration.
 *
 * @param array{
 *     host: string,
 *     username: string,
 *     password: string,
 *     name: string,
 *     charset: string,
 *     collation: string
 * } $config
 */
function createPdoConnection(array $config): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['name'],
        $config['charset']
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = sprintf(
            "SET NAMES %s COLLATE %s",
            $config['charset'],
            $config['collation']
        );
    }

    return new PDO($dsn, $config['username'], $config['password'], $options);
}

/**
 * Handles the replication process.
 */
final class ReplicationProgress
{
    private array $state = [
        'tables_completed' => [],
        'views_completed' => [],
        'triggers_completed' => [],
    ];

    public function __construct(private readonly string $path)
    {
        $this->load();
    }

    public function isTableCompleted(string $name): bool
    {
        return !empty($this->state['tables_completed'][$name]);
    }

    public function markTableCompleted(string $name): void
    {
        if ($this->isTableCompleted($name)) {
            return;
        }

        $this->state['tables_completed'][$name] = true;
        $this->save();
    }

    public function forgetTable(string $name): void
    {
        if (!empty($this->state['tables_completed'][$name])) {
            unset($this->state['tables_completed'][$name]);
            $this->save();
        }
    }

    public function isViewCompleted(string $name): bool
    {
        return !empty($this->state['views_completed'][$name]);
    }

    public function markViewCompleted(string $name): void
    {
        if ($this->isViewCompleted($name)) {
            return;
        }

        $this->state['views_completed'][$name] = true;
        $this->save();
    }

    public function forgetView(string $name): void
    {
        if (!empty($this->state['views_completed'][$name])) {
            unset($this->state['views_completed'][$name]);
            $this->save();
        }
    }

    public function isTriggerCompleted(string $name): bool
    {
        return !empty($this->state['triggers_completed'][$name]);
    }

    public function markTriggerCompleted(string $name): void
    {
        if ($this->isTriggerCompleted($name)) {
            return;
        }

        $this->state['triggers_completed'][$name] = true;
        $this->save();
    }

    public function forgetTrigger(string $name): void
    {
        if (!empty($this->state['triggers_completed'][$name])) {
            unset($this->state['triggers_completed'][$name]);
            $this->save();
        }
    }

    public function clear(): void
    {
        $this->state = [
            'tables_completed' => [],
            'views_completed' => [],
            'triggers_completed' => [],
        ];

        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }

    private function load(): void
    {
        if (!is_file($this->path) || !is_readable($this->path)) {
            return;
        }

        $raw = file_get_contents($this->path);
        if ($raw === false || trim($raw) === '') {
            return;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return;
        }

        foreach (['tables_completed', 'views_completed', 'triggers_completed'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                // Normalize to associative array for quick lookups.
                $this->state[$key] = [];
                foreach ($decoded[$key] as $name => $value) {
                    if (is_string($name)) {
                        $this->state[$key][$name] = (bool) $value;
                    } elseif (is_string($value)) {
                        $this->state[$key][$value] = true;
                    }
                }
            }
        }
    }

    private function save(): void
    {
        $json = json_encode(
            $this->state,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new RuntimeException('Unable to encode replication progress to JSON.');
        }

        if (file_put_contents($this->path, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write replication progress file.');
        }
    }
}

final class DatabaseCopier
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly PDO $source,
        private readonly PDO $target,
        private readonly ReplicationProgress $progress,
        private readonly string $sourceDatabase,
        private readonly string $targetDatabase
    ) {
    }

    public function run(): void
    {
        $this->target->exec('SET FOREIGN_KEY_CHECKS=0');

        try {
            $tables = $this->getTables('BASE TABLE');
            $views = $this->getTables('VIEW');

            $this->copyTables($tables);
            $this->copyViews($views);
            $this->copyTriggers();
        } finally {
            $this->target->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * @return array<int, string>
     */
    private function getTables(string $type): array
    {
        $sql = "SHOW FULL TABLES WHERE Table_type = :type";
        $stmt = $this->source->prepare($sql);
        $stmt->execute(['type' => $type]);

        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        return $tables;
    }

    /**
     * @param array<int, string> $tables
     */
    private function copyTables(array $tables): void
    {
        foreach ($tables as $table) {
            $hasTarget = $this->targetHasBaseTable($table);

            if ($this->progress->isTableCompleted($table) && $hasTarget) {
                emit_log("Skipping table {$table} (already replicated).");
                continue;
            }

            if ($this->progress->isTableCompleted($table) && !$hasTarget) {
                $this->progress->forgetTable($table);
            }

            $totalRows = $this->countRows($table);
            emit_log("Copying table {$table} ({$totalRows} rows)...");

            $this->target->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));

            $createSql = $this->getCreateStatement($table, false);
            $this->target->exec($createSql);

            $columns = $this->getColumnNames($table);
            if (empty($columns)) {
                emit_log("Finished table {$table} (no columns detected).");
                 $this->progress->markTableCompleted($table);
                continue;
            }

            $columnList = implode(',', array_map(static fn ($col) => "`{$col}`", $columns));
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $insertSql = sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $table, $columnList, $placeholders);
            $insertStmt = $this->target->prepare($insertSql);

            $selectStmt = $this->source->prepare(sprintf('SELECT * FROM `%s`', $table));
            $this->disableBufferedQuery($selectStmt);
            $selectStmt->execute();

            $batch = [];
            $processed = 0;
            while ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
                $batch[] = array_values($row);

                if (count($batch) >= self::CHUNK_SIZE) {
                    $this->insertBatch($insertStmt, $batch);
                    $processed += count($batch);
                    $this->emitProgress($table, $processed, $totalRows);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                $this->insertBatch($insertStmt, $batch);
                $processed += count($batch);
                $this->emitProgress($table, $processed, $totalRows);
            }

            if ($processed < $totalRows) {
                $this->emitProgress($table, $totalRows, $totalRows);
            }

            emit_log("Finished table {$table} ({$processed} rows copied).");
            $this->progress->markTableCompleted($table);
        }
    }

    private function disableBufferedQuery(PDOStatement $stmt): void
    {
        if (!defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            return;
        }

        try {
            $stmt->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        } catch (PDOException $e) {
            // Some drivers (e.g., mysqlnd on older PHP builds) do not support toggling this attribute.
        }
    }

    /**
     * @param PDOStatement $stmt
     * @param array<int, array<int|null|string>> $batch
     */
    private function insertBatch(PDOStatement $stmt, array $batch): void
    {
        foreach ($batch as $row) {
            $stmt->execute($row);
        }
    }

    private function countRows(string $table): int
    {
        $stmt = $this->source->query(sprintf('SELECT COUNT(*) AS total FROM `%s`', $table));
        $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        return (int) ($result['total'] ?? 0);
    }

    private function emitProgress(string $table, int $processed, int $total): void
    {
        if ($total > 0) {
            $clamped = min($processed, $total);
            $percent = number_format(($clamped / $total) * 100, 2);
            $remaining = max($total - $clamped, 0);
            emit_log(sprintf('[%s] %d / %d rows (%s%%, %d remaining)', $table, $clamped, $total, $percent, $remaining));
        } else {
            emit_log(sprintf('[%s] %d rows copied', $table, $processed));
        }
    }

    private function targetHasBaseTable(string $table): bool
    {
        $sql = 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND TABLE_TYPE = :type';
        $stmt = $this->target->prepare($sql);
        $stmt->execute([
            'schema' => $this->targetDatabase,
            'table' => $table,
            'type' => 'BASE TABLE',
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function targetHasView(string $view): bool
    {
        $sql = 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND TABLE_TYPE = :type';
        $stmt = $this->target->prepare($sql);
        $stmt->execute([
            'schema' => $this->targetDatabase,
            'table' => $view,
            'type' => 'VIEW',
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function targetHasTrigger(string $trigger): bool
    {
        $sql = 'SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = :schema AND TRIGGER_NAME = :trigger';
        $stmt = $this->target->prepare($sql);
        $stmt->execute([
            'schema' => $this->targetDatabase,
            'trigger' => $trigger,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return array<string, bool>
     */
    private function getTargetViews(): array
    {
        $sql = 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_TYPE = :type';
        $stmt = $this->target->prepare($sql);
        $stmt->execute([
            'schema' => $this->targetDatabase,
            'type' => 'VIEW',
        ]);

        $views = [];
        while ($name = $stmt->fetchColumn()) {
            if (is_string($name)) {
                $views[$name] = true;
            }
        }

        return $views;
    }

    /**
     * @return array<string, bool>
     */
    private function getTargetTriggers(): array
    {
        $sql = 'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = :schema';
        $stmt = $this->target->prepare($sql);
        $stmt->execute([
            'schema' => $this->targetDatabase,
        ]);

        $triggers = [];
        while ($name = $stmt->fetchColumn()) {
            if (is_string($name)) {
                $triggers[$name] = true;
            }
        }

        return $triggers;
    }

    /**
     * @param array<int, string> $views
     */
    private function copyViews(array $views): void
    {
        $existingTargetViews = $this->getTargetViews();
        $existingNames = array_keys($existingTargetViews);

        $obsolete = array_diff($existingNames, $views);
        foreach ($obsolete as $view) {
            emit_log("Dropping target-only view {$view}...");
            $this->target->exec(sprintf('DROP VIEW IF EXISTS `%s`', $view));
            $this->progress->forgetView($view);
            unset($existingTargetViews[$view]);
        }

        foreach ($views as $view) {
            $hasTarget = isset($existingTargetViews[$view]) && $this->targetHasView($view);

            if ($this->progress->isViewCompleted($view) && $hasTarget) {
                emit_log("Skipping view {$view} (already replicated).");
                continue;
            }

            if ($this->progress->isViewCompleted($view) && !$hasTarget) {
                $this->progress->forgetView($view);
            }

            emit_log("Copying view {$view}...");
            $this->target->exec(sprintf('DROP VIEW IF EXISTS `%s`', $view));

            $createSql = $this->getCreateStatement($view, true);
            $this->target->exec($createSql);
            $this->progress->markViewCompleted($view);
            $existingTargetViews[$view] = true;
            emit_log("Finished view {$view}.");
        }
    }

    private function copyTriggers(): void
    {
        $triggerNames = $this->source->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
        $existingTargetTriggers = $this->getTargetTriggers();
        $targetTriggerNames = array_keys($existingTargetTriggers);

        $obsolete = array_diff($targetTriggerNames, $triggerNames);
        foreach ($obsolete as $trigger) {
            emit_log("Dropping target-only trigger {$trigger}...");
            $this->target->exec(sprintf('DROP TRIGGER IF EXISTS `%s`', $trigger));
            $this->progress->forgetTrigger($trigger);
            unset($existingTargetTriggers[$trigger]);
        }

        foreach ($triggerNames as $trigger) {
            $hasTarget = isset($existingTargetTriggers[$trigger]) && $this->targetHasTrigger($trigger);

            if ($this->progress->isTriggerCompleted($trigger) && $hasTarget) {
                emit_log("Skipping trigger {$trigger} (already replicated).");
                continue;
            }

            if ($this->progress->isTriggerCompleted($trigger) && !$hasTarget) {
                $this->progress->forgetTrigger($trigger);
            }

            emit_log("Copying trigger {$trigger}...");
            $createStmt = $this->source->query(sprintf('SHOW CREATE TRIGGER `%s`', $trigger));
            $row = $createStmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !isset($row['SQL Original Statement'])) {
                continue;
            }

            $createSql = $this->stripDefiner($row['SQL Original Statement']);
            $this->target->exec(sprintf('DROP TRIGGER IF EXISTS `%s`', $trigger));
            $this->target->exec($createSql);
            $this->progress->markTriggerCompleted($trigger);
            $existingTargetTriggers[$trigger] = true;
            emit_log("Finished trigger {$trigger}.");
        }
    }

    /**
     * @return array<int, string>
     */
    private function getColumnNames(string $table): array
    {
        $stmt = $this->source->prepare(sprintf('DESCRIBE `%s`', $table));
        $stmt->execute();

        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }

        return $columns;
    }

    private function getCreateStatement(string $object, bool $isView): string
    {
        $sql = $isView
            ? sprintf('SHOW CREATE VIEW `%s`', $object)
            : sprintf('SHOW CREATE TABLE `%s`', $object);

        $stmt = $this->source->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException("Unable to fetch CREATE statement for {$object}");
        }

        $key = $isView ? 'Create View' : 'Create Table';
        if (!isset($row[$key])) {
            throw new RuntimeException("CREATE statement missing expected key for {$object}");
        }

        $createSql = $row[$key];
        return $this->stripDefiner($createSql);
    }

    private function stripDefiner(string $sql): string
    {
        return preg_replace('/\sDEFINER=`[^`]+`@`[^`]+`\s/', ' ', $sql) ?? $sql;
    }
}

function bootstrap(string $rootDir): void
{
    if (PHP_SAPI === 'cli') {
        main($rootDir);
        return;
    }

    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (strtoupper($requestMethod) === 'POST') {
        prepareStreamingResponse();
        emit_log('Starting database replication...');

        try {
            main($rootDir);
        } catch (Throwable $exception) {
            http_response_code(500);
            emit_log('ERROR: ' . $exception->getMessage());
            emit_log(sprintf('Location: %s:%d', $exception->getFile(), $exception->getLine()));
        }

        return;
    }

    renderControlPanel($rootDir);
}

function prepareStreamingResponse(): void
{
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
    }

    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', '0');

    while (ob_get_level() > 0) {
        @ob_end_flush();
    }

    ob_implicit_flush(true);
    echo str_repeat(' ', 2048);
    flush();
}

function renderControlPanel(string $rootDir): void
{
    loadEnvFile($rootDir . DIRECTORY_SEPARATOR . '.env');

    $sourceName = getenv('APP_DB_NAME') ?: 'Not configured';
    $sourceHost = getenv('APP_DB_HOSTNAME') ?: 'Not configured';
    $targetName = getenv('REPL_TARGET_DB_NAME') ?: 'Not configured';
    $targetHost = getenv('REPL_TARGET_DB_HOSTNAME') ?: 'Not configured';

    $escape = static function (?string $value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Database Replication Utility</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                margin: 40px;
                color: #1f2933;
                background-color: #f8f9fb;
            }
            h1 {
                margin-top: 0;
            }
            .panel {
                background: #fff;
                border-radius: 8px;
                padding: 24px;
                box-shadow: 0 10px 30px rgba(15, 23, 42, 0.1);
            }
            .env-details {
                margin-bottom: 20px;
                padding: 16px;
                border: 1px solid #d2d6dc;
                border-radius: 6px;
                background-color: #f1f5f9;
                font-size: 14px;
            }
            button {
                padding: 12px 24px;
                font-size: 16px;
                border: none;
                border-radius: 6px;
                background-color: #2563eb;
                color: #fff;
                cursor: pointer;
                transition: background-color 0.2s ease;
            }
            button:hover:not(:disabled) {
                background-color: #1d4ed8;
            }
            button:disabled {
                background-color: #94a3b8;
                cursor: not-allowed;
            }
            #status {
                margin-left: 12px;
                font-weight: 600;
            }
            pre {
                margin-top: 24px;
                padding: 16px;
                background: #0f172a;
                color: #e2e8f0;
                max-height: 480px;
                overflow-y: auto;
                border-radius: 6px;
                font-size: 13px;
                line-height: 1.5;
            }
            .note {
                margin-top: 16px;
                font-size: 14px;
                color: #475467;
            }
        </style>
    </head>
    <body>
        <div class="panel">
            <h1>Database Replication Utility</h1>
            <p class="note">
                Press the button below to overwrite the target database with a fresh copy of the source database.
                Make sure no critical operations are running before starting the replication.
            </p>
            <div class="env-details">
                <div><strong>Source:</strong> <?php echo $escape($sourceHost); ?> / <?php echo $escape($sourceName); ?></div>
                <div><strong>Target:</strong> <?php echo $escape($targetHost); ?> / <?php echo $escape($targetName); ?></div>
            </div>
            <button id="replicate-btn" type="button">Start Database Copy</button>
            <span id="status">Idle</span>
            <pre id="log" aria-live="polite"></pre>
        </div>
        <script>
            (function () {
                const button = document.getElementById('replicate-btn');
                const status = document.getElementById('status');
                const log = document.getElementById('log');

                async function runReplication() {
                    if (!confirm('This will overwrite the target database. Continue?')) {
                        return;
                    }

                    button.disabled = true;
                    log.textContent = '';
                    status.textContent = 'Running...';

                    try {
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                        });

                        const reader = response.body ? response.body.getReader() : null;
                        const decoder = new TextDecoder();

                        if (reader) {
                            let done = false;
                            while (!done) {
                                const result = await reader.read();
                                done = result.done;
                                if (result.value) {
                                    log.textContent += decoder.decode(result.value, {stream: !done});
                                    log.scrollTop = log.scrollHeight;
                                }
                            }
                        } else {
                            const text = await response.text();
                            log.textContent += text;
                        }

                        if (response.ok) {
                            status.textContent = 'Completed';
                        } else {
                            status.textContent = 'Failed';
                        }
                    } catch (error) {
                        log.textContent += '\nERROR: ' + error.message + '\n';
                        status.textContent = 'Failed';
                    } finally {
                        button.disabled = false;
                    }
                }

                button.addEventListener('click', runReplication);
            }());
        </script>
    </body>
    </html>
    <?php
}

function emit_log(string $message = ''): void
{
    $output = $message;

    if ($output === '' || !preg_match('/\r?\n$/', $output)) {
        $output .= PHP_EOL;
    }

    fwrite(STDOUT, $output);

    if (PHP_SAPI !== 'cli') {
        @ob_flush();
        flush();
    }
}

function starts_with(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }

    return strncmp($haystack, $needle, strlen($needle)) === 0;
}

function ends_with(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }

    return substr($haystack, -strlen($needle)) === $needle;
}
