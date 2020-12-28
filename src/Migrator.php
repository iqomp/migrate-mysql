<?php

/**
 * MySQL migrator
 * @package iqomp/migrate-mysql
 * @version 1.0.0
 */

namespace Iqomp\MigrateMysql;

use Iqomp\Migrate\MigratorInterface;
use Symfony\Component\Console\Input\InputInterface as In;
use Symfony\Component\Console\Output\OutputInterface as Out;

class Migrator implements MigratorInterface
{
    protected $last_error;

    protected $in;
    protected $out;
    protected $config;

    protected $conn;

    protected $classmap = [
        'mysqli' => 'mysqli',
        'Cli'    => 'Iqomp\\MigrateMysql\\Cli',
        'Sql'    => 'Iqomp\\MigrateMysql\\Database\\QueryBuilder',
        'Syncer' => 'Iqomp\\MigrateMysql\\Syncer'
    ];

    protected function getClassMap(string $name): ?string
    {
        return $this->classmap[$name] ?? null;
    }

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
            $this->out->writeln('<error>' . $result . '</error>');
        }
    }

    public function __construct(In $in, Out $out, array $config)
    {
        $this->in  = $in;
        $this->out = $out;

        if (!isset($config['charset'])) {
            $config['charset'] = 'utf8mb4';
        }

        if (isset($config['classmap'])) {
            foreach ($config['classmap'] as $name => $class) {
                $this->classmap[$name] = $class;
            }
            unset($config['classmap']);
        }

        $this->config = $config;

        if (!isset($config['dbname'])) {
            $this->setError('No DB name provided on config');
        } else {
            $mysqli = $this->getClassMap('mysqli');
            $this->createConnection($mysqli);
        }
    }

    public function activateDb(): void
    {
        $dbname = $this->config['dbname'];

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
        $user = $this->config['user'] ?? null;
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
        $name    = $this->config['dbname'];
        $collate = $this->config['collation'] ?? null;

        $this->out->writeln('<info>Creating database `' . $name . '`</info>');

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
        $dbname = $this->config['dbname'];

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

        $syncer = $this->getClassMap('Syncer');
        $sqlb   = $this->getClassMap('Sql');
        $cli    = $this->getClassMap('Cli');

        $config['connection'] = $this->config;

        $result = $syncer::compare($this->conn, $model, $table, $config);
        if (!$result) {
            return;
        }

        $sqls = $sqlb::build($result, $this->conn);

        if ($sqls) {
            $this->out->write($model);
            foreach ($sqls as $index => $sql) {
                $this->out->write('.');
                $res = $this->query($sql);
                if (!$res) {
                    $this->out->writeln('');
                    $this->setError($this->conn->error, $this->conn->errno);
                }
            }
            $this->out->writeln('');
        }
    }

    public function syncTableTo(string $model, string $table, array $config): void
    {
        if (!$this->dbExists()) {
            $this->setError('Target database not found');
            return;
        }

        $syncer = $this->getClassMap('Syncer');
        $sqlb   = $this->getClassMap('Sql');

        $config['connection'] = $this->config;

        $result = $syncer::compare($this->conn, $model, $table, $config);
        if (!$result) {
            return;
        }

        $nl = PHP_EOL;

        $result = $sqlb::build($result, $this->conn);
        if (!$result) {
            return;
        }

        $sql = '-- ' . $model
             . $nl
             . implode($nl, $result)
             . $nl
             . $nl;

        $this->out->write($sql);
    }

    public function testTable(string $model, string $table, array $config): void
    {
        if (!$this->dbExists()) {
            $this->setError('Target database not found');
            return;
        }

        $syncer = $this->getClassMap('Syncer');
        $cli    = $this->getClassMap('Cli');

        $config['connection'] = $this->config;

        $final = $syncer::compare($this->conn, $model, $table, $config);
        if (!$final['result']) {
            return;
        }

        $cli::diff($final, $this->in, $this->out);
    }
}
