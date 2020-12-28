<?php

/**
 * Table index query builder
 * @package iqomp/migarte-mysql
 * @version 1.0.0
 */

namespace Iqomp\MigrateMysql\Database;

class QueryIndex implements QueryBuilderInterface
{
    protected static $charset;
    protected static $collate;
    protected static $options;
    protected static $table;

    public static function create(string $table, array $indexes): array
    {
        $result = [];

        $index_types = ['FULLTEXT', 'UNIQUE', 'SPATIAL'];

        foreach ($indexes as $name => $index) {
            $sql = 'CREATE';
            if (in_array($index['type'], $index_types)) {
                $sql .= ' ' . $index['type'];
            }
            $sql .= " INDEX `{$index['name']}` ON `{$table}`";

            $fields = [];

            foreach ($index['fields'] as $name => $opts) {
                $field = "`{$name}`";
                if (isset($opts['length'])) {
                    $field .= "({$opts['length']})";
                }

                $fields[] = $field;
            }

            $sql .= ' (' . implode(',', $fields) . ')';

            if (in_array($index['type'], ['BTREE', 'HASH'])) {
                $sql .= ' USING ' . $index['type'];
            }

            $sql .= ';';

            $result[] = $sql;
        }

        return $result;
    }

    public static function remove(string $table, array $indexes): array
    {
        foreach ($indexes as $cnf_index) {
            $cnf_index_name = $cnf_index['name'];
            $result[] = "DROP INDEX `$cnf_index_name` ON `$table`;";
        }

        return $result;
    }

    public static function setOptions(array $options): void
    {
        $conns = $options['config']['connection'];

        self::$charset = $conns['charset'];
        self::$collate = $conns['collation'];
        self::$options = $options;
    }

    public static function update(string $table, array $indexes): array
    {
        $result[] = self::remove($table, $indexes);
        $result[] = self::create($table, $indexes);

        return $result;
    }
}
