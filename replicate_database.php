<?php
/**
 * Simple database replication utility.
 *
 * Usage:
 *   php replicate_database.php
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

main(__DIR__);

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

    $copier = new DatabaseCopier($sourcePdo, $targetPdo);
    $copier->run();

    fwrite(STDOUT, PHP_EOL . "Database copy completed successfully." . PHP_EOL);
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
final class DatabaseCopier
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly PDO $source,
        private readonly PDO $target
    ) {
    }

    public function run(): void
    {
        $this->target->exec('SET FOREIGN_KEY_CHECKS=0');

        try {
            $tables = $this->getTables('BASE TABLE');
            $views = $this->getTables('VIEW');

            $this->resetTargetTables($tables);
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
     * Drops matching tables in target before copying over the new definition.
     *
     * @param array<int, string> $tables
     */
    private function resetTargetTables(array $tables): void
    {
        if (empty($tables)) {
            return;
        }

        foreach ($tables as $table) {
            $this->target->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
    }

    /**
     * @param array<int, string> $tables
     */
    private function copyTables(array $tables): void
    {
        foreach ($tables as $table) {
            fwrite(STDOUT, "Copying table {$table}..." . PHP_EOL);

            $createSql = $this->getCreateStatement($table, false);
            $this->target->exec($createSql);

            $columns = $this->getColumnNames($table);
            if (empty($columns)) {
                continue;
            }

            $columnList = implode(',', array_map(static fn ($col) => "`{$col}`", $columns));
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $insertSql = sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $table, $columnList, $placeholders);
            $insertStmt = $this->target->prepare($insertSql);

            $selectStmt = $this->source->prepare(sprintf('SELECT * FROM `%s`', $table));
            if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
                $selectStmt->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            }
            $selectStmt->execute();

            $batch = [];
            while ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
                $batch[] = array_values($row);

                if (count($batch) >= self::CHUNK_SIZE) {
                    $this->insertBatch($insertStmt, $batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                $this->insertBatch($insertStmt, $batch);
            }
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

    /**
     * @param array<int, string> $views
     */
    private function copyViews(array $views): void
    {
        if (empty($views)) {
            return;
        }

        foreach ($views as $view) {
            fwrite(STDOUT, "Copying view {$view}..." . PHP_EOL);
            $this->target->exec(sprintf('DROP VIEW IF EXISTS `%s`', $view));

            $createSql = $this->getCreateStatement($view, true);
            $this->target->exec($createSql);
        }
    }

    private function copyTriggers(): void
    {
        $this->dropExistingTriggers();

        $triggerNames = $this->source->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_COLUMN, 0);
        if (empty($triggerNames)) {
            return;
        }

        foreach ($triggerNames as $trigger) {
            fwrite(STDOUT, "Copying trigger {$trigger}..." . PHP_EOL);
            $createStmt = $this->source->query(sprintf('SHOW CREATE TRIGGER `%s`', $trigger));
            $row = $createStmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !isset($row['SQL Original Statement'])) {
                continue;
            }

            $createSql = $this->stripDefiner($row['SQL Original Statement']);
            $this->target->exec($createSql);
        }
    }

    private function dropExistingTriggers(): void
    {
        $existing = $this->target->query('SHOW TRIGGERS');
        if ($existing === false) {
            return;
        }

        $triggers = $existing->fetchAll(PDO::FETCH_COLUMN, 0);
        foreach ($triggers as $trigger) {
            $this->target->exec(sprintf('DROP TRIGGER IF EXISTS `%s`', $trigger));
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

