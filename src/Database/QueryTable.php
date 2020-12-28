<?php

/**
 * Table builder
 * @package iqomp/migrate-mysql
 * @version 1.0.0
 */

namespace Iqomp\MigrateMysql\Database;

class QueryTable implements QueryBuilderInterface
{
    protected static $charset;
    protected static $collate;
    protected static $options;

    public static function setOptions(array $options): void
    {
        $conns = $options['config']['connection'];

        self::$charset = $conns['charset'];
        self::$collate = $conns['collation'];
        self::$options = $options;
    }

    public static function create(string $table, array $cnf_fields): array
    {
        $tx  = "CREATE TABLE IF NOT EXISTS `$table` ";
        $nl  = PHP_EOL;
        $sep = $nl . '    ';

        $sql_rows     = [];
        $primary_keys = [];
        $charset      = self::$charset;
        $collate      = self::$collate;

        QueryColumn::setOptions(self::$options);
        foreach ($cnf_fields as $field) {
            $sql_rows[] = QueryColumn::meta($field, false);
            if ($field['attrs']['primary_key']) {
                $primary_keys[] = $field['name'];
            }
        }

        if ($primary_keys) {
            $primary_str = '`' . implode('`,`', $primary_keys) . '`';
            $sql_rows[] = 'PRIMARY KEY (' . $primary_str . ')';
        }

        $sql_str = implode(',' . $sep, $sql_rows);
        $tx .= "({$sep}{$sql_str}{$nl}) ";
        $tx .= "DEFAULT CHARACTER SET {$charset} COLLATE {$collate};";

        return [$tx];
    }
}
