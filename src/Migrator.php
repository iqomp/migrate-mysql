<?php

/**
 * MySQL migrator
 * @package iqomp/migrate-mysql
 * @version 2.0.0
 */

namespace Iqomp\MigrateMysql;

use Iqomp\Migrate\MigratorInterface;
use Iqomp\MigrateMysql\Database\QueryBuilder as QBuilder;
use Hyperf\Command\Command as HyperfCommand;

class Migrator implements MigratorInterface
{
    protected $last_error;
    protected $cli;
    protected $config;
    protected $conn;

    protected function query(string $sql)
    {
        $result = $this->conn->query($sql);
        if (is_bool($result)) {
            return $result;
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

        return $rows;
    }

    protected function setError(string $message, string $code = '', bool $print = true)
    {
        $result = '';
        if ($code) {
            $result .= '(' . $code . ') ';
        }

        $result .= $message;

        $this->last_error = $result;

        if ($print) {
            $this->cli->error($result);
        }
    }

    public function __construct(HyperfCommand $cli, array $config)
    {
        $this->cli = $cli;

        if (!isset($config['charset'])) {
            $config['charset'] = 'utf8mb4';
        }

        if (!isset($config['collation'])) {
            $config['collation'] = 'utf8mb4_general_ci';
        }

        if (isset($config['classmap'])) {
            foreach ($config['classmap'] as $name => $class) {
                $this->classmap[$name] = $class;
            }
            unset($config['classmap']);
        }

        $this->config = $config;

        if (!isset($config['database'])) {
            $this->setError('No DB name provided on config');
        } else {
            $this->createConnection('mysqli');
        }
    }

    public function activateDb(): void
    {
        $dbname = $this->config['database'];

        $this->conn->select_db($dbname);

        $collation = $this->config['collation'] ?? null;
        $charset   = $this->config['charset'];

        $this->conn->set_charset($charset);

        if ($collation) {
            $sql = "SET NAMES '$charset' COLLATE '$collation'";
            $this->query($sql);
        }
    }

    public function createConnection(string $connector): void
    {
        $host = $this->config['host'] ?? null;
        $user = $this->config['username'] ?? null;
        $pass = $this->config['passwd'] ?? null;

        $this->conn = new $connector($host, $user, $pass);

        if ($this->conn->connect_error) {
            $e_code = $this->conn->connect_errno;
            $e_msg  = $this->conn->connect_error;

            $this->setError($e_msg, $e_code);
        }
    }

    public function createDb(): bool
    {
        if ($this->last_error) {
            return false;
        }

        $charset = $this->config['charset'];
        $name    = $this->config['database'];
        $collate = $this->config['collation'] ?? null;

        $this->cli->info('Creating database `' . $name . '`');

        $sql = "CREATE DATABASE `$name` CHARACTER SET $charset";
        if ($collate) {
            $sql .= " COLLATE $collate";
        }

        $result = $this->query($sql);

        if (!$result) {
            $this->setError($this->conn->error, $this->conn->errno, false);
            return false;
        }

        return true;
    }

    public function dbExists(): bool
    {
        $rows   = $this->query('SHOW DATABASES;');
        $exists = false;
        $dbname = $this->config['database'];

        foreach ($rows as $row) {
            if ($row['Database'] === $dbname) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            return false;
        }

        $this->activateDb();

        return true;
    }

    public function lastError(): ?string
    {
        return $this->last_error;
    }

    public function syncTable(string $model, string $table, array $config): void
    {
        if (!$this->dbExists()) {
            $this->setError('Target database not found');
            return;
        }

        $config['connection'] = $this->config;

        $result = Syncer::compare($this->conn, $model, $table, $config);
        if (!$result) {
            return;
        }

        $sqls = QBuilder::build($result, $this->conn);

        if ($sqls) {
            $this->cli->line($model);
            foreach ($sqls as $index => $sql) {
                $res = $this->query($sql);
                if (!$res) {
                    $this->cli->line('');
                    $this->setError($this->conn->error, $this->conn->errno);
                }
            }
            $this->cli->line('');
        }
    }

    public function syncTableTo(string $model, string $table, array $config): void
    {
        if (!$this->dbExists()) {
            $this->setError('Target database not found');
            return;
        }

        $config['connection'] = $this->config;

        $result = Syncer::compare($this->conn, $model, $table, $config);
        if (!$result) {
            return;
        }

        $nl = PHP_EOL;

        $result = QBuilder::build($result, $this->conn);
        if (!$result) {
            return;
        }

        $sql = '-- ' . $model
             . $nl
             . implode($nl, $result)
             . $nl
             . $nl;

        $this->cli->line($sql);
    }

    public function testTable(string $model, string $table, array $config): void
    {
        if (!$this->dbExists()) {
            $this->setError('Target database not found');
            return;
        }

        $config['connection'] = $this->config;

        $final = Syncer::compare($this->conn, $model, $table, $config);
        if (!$final['result']) {
            return;
        }

        Cli::diff($final, $this->cli);
    }
}
