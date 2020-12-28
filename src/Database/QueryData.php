<?php

/**
 * Data create query builder
 * @package iqomp/migrate-mysql
 * @version 1.0.0
 */

namespace Iqomp\MigrateMysql\Database;

class QueryData implements QueryBuilderInterface
{
    protected static $charset;
    protected static $collate;
    protected static $options;

    public static function create(string $table, array $data, &$conn): array
    {
        $result = [];

        foreach ($data as $row) {
            $result[] = self::createSingle($table, $row, $conn);
        }

        return $result;
    }

    public static function createSingle(string $table, array $data, &$conn): string
    {
        $fields = array_keys($data);
        $fields = '`' . implode('`, `', $fields) . '`';

        $values = array_values($data);
        $values = array_map(function ($value) use ($conn) {
            return mysqli_real_escape_string($conn, $value);
        }, $values);

        $values = '\'' . implode('\', \'', $values) . '\'';

        $sql = "INSERT INTO `$table` ( $fields ) VALUES ( $values );";

        return $sql;
    }

    public static function setOptions(array $options): void
    {
        $conns = $options['config']['connection'];

        self::$charset = $conns['charset'];
        self::$collate = $conns['collation'];
        self::$options = $options;
    }
}
