<?php

/**
 * Column query builder
 * @package iqomp/migrate-mysql
 * @version 1.0.0
 */

namespace Iqomp\MigrateMysql\Database;

class QueryColumn implements QueryBuilderInterface
{
    protected static $charset;
    protected static $collate;
    protected static $options;
    protected static $table;

    protected static function change(array $cnf_fields, string $type, &$conn): array
    {
        $config = self::$options['config'];
        $table  = self::$table;

        $tbl_fields = $config['fields'];

        $charset = self::$charset;
        $collate = self::$collate;

        $result = [];

        // we'll need to make sure the primary key is not re-assigned
        // to the column
        $db_fields = Descriptor::table($conn, $table);

        foreach ($cnf_fields as $cnf_field) {
            $prev_field = null;
            foreach ($tbl_fields as $tbl_field_name => $tbl_field) {
                if ($tbl_field_name != $cnf_field['name']) {
                    $prev_field = $tbl_field_name;
                    continue;
                }

                $tbl_field['name'] = $tbl_field_name;

                $with_pk = true;

                if (isset($db_fields[$tbl_field_name])) {
                    if ($db_fields[$tbl_field_name]['attrs']['primary_key']) {
                        $with_pk = false;
                    }
                }

                $line = "ALTER TABLE `$table` $type ";
                $line .= self::meta($cnf_field, $with_pk, $charset, $collate);

                if ($prev_field) {
                    $line .= " AFTER `$prev_field`;";
                } else {
                    $line .= ' FIRST;';
                }

                $result[] = $line;
                break;
            }
        }

        return $result;
    }

    public static function setOptions(array $options): void
    {
        $conns = $options['config']['connection'];

        self::$table   = $options['table'];
        self::$charset = $conns['charset'];
        self::$collate = $conns['collation'];
        self::$options = $options;
    }

    public static function meta(array $field, bool $with_pk): string
    {
        $fname = $field['name'];
        $ftype = $field['type'];

        $charset = self::$charset;
        $collate = self::$collate;

        $sql = "`{$fname}` {$ftype}";

        if ($field['attrs']['length']) {
            $flen = $field['attrs']['length'];
            $sql .= "({$flen})";
        }

        if ($field['attrs']['options']) {
            $fopts = '\'' . implode("','", $field['attrs']['options']) . '\'';
            $sql .= "(${$fopts})";
        }

        if ($field['attrs']['unsigned']) {
            $sql .= ' UNSIGNED';
        }

        $column_with_charset = [
            'CHAR',
            'ENUM',
            'LONGTEXT',
            'SET',
            'TEXT',
            'TINYTEXT',
            'VARCHAR'
        ];

        if (in_array($field['type'], $column_with_charset)) {
            $sql .= " CHARACTER SET {$charset} COLLATE {$collate}";
        }

        if (!$field['attrs']['null']) {
            $sql .= ' NOT NULL';
        }

        if ($field['attrs']['unique']) {
            $sql .= ' UNIQUE';
        }

        $default = $field['attrs']['default'];
        if (!is_null($default)) {
            $sql .= ' DEFAULT ';

            if ($default === 'CURRENT_TIMESTAMP') {
                $sql .= 'CURRENT_TIMESTAMP';
            } elseif (substr($default, -2) === '()') {
                $sql .= $default;
            } elseif ($field['type'] === 'BOOLEAN') {
                $sql .= $default ? 'TRUE' : 'FALSE';
            } else {
                $sql .= "'{$default}'";
            }
        }

        $update = $field['attrs']['update'];
        if (!is_null($update)) {
            $sql .= ' ON UPDATE ';

            if ($update === 'CURRENT_TIMESTAMP') {
                $sql .= 'CURRENT_TIMESTAMP';
            } elseif (substr($update, -2) === '()') {
                $sql .= $update;
            } elseif ($field['type'] === 'BOOLEAN') {
                $sql .= $update ? 'TRUE' : 'FALSE';
            } else {
                $sql .= "'{$update}'";
            }
        }

        if ($field['attrs']['auto_increment']) {
            $sql .= ' AUTO_INCREMENT';
        }

        if ($with_pk && $field['attrs']['primary_key']) {
            $sql .= ' PRIMARY KEY';
        }

        return $sql;
    }

    public static function create(string $table, array $fields, &$conn): array
    {
        return self::change($fields, 'ADD COLUMN', $conn);
    }

    public static function remove(string $table, array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            $sql = "ALTER TABLE `$table` DROP COLUMN `$field`;";
            $result[] = $sql;
        }

        return $result;
    }

    public static function update(string $table, array $fields, &$conn): array
    {
        return self::change($fields, 'MODIFY', $conn);
    }
}
