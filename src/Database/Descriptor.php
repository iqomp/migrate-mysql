<?php

/**
 * Table real to config comparition
 * @package iqomp/migrate-mysql
 * @version 1.0.0
 */

namespace Iqomp\MigrateMysql\Database;

class Descriptor
{
    public static function index($conn, string $table, array $fields): ?array
    {
        $sql = "SHOW INDEXES FROM `$table`;";
        $result = $conn->query($sql);
        if (!$result) {
            return null;
        }

        $tbl_indexes = $result->fetch_all(MYSQLI_ASSOC);

        $result = [];

        foreach ($tbl_indexes as $index) {
            $name = $index['Key_name'];

            // skip primary name
            if ($name === 'PRIMARY') {
                continue;
            }

            // is unique/primary key
            if (isset($fields[$name])) {
                $is_unique  = $fields[$name]['attrs']['unique'] ?? false;
                $is_primary = $fields[$name]['attrs']['primary_key'] ?? false;
                if ($is_unique || $is_primary) {
                    continue;
                }
            }

            if (!isset($result[$name])) {
                $result[$name] = [
                    'name'   => $name,
                    'type'   => $index['Index_type'],
                    'fields' => []
                ];
            }

            $idx = [];
            if ($index['Sub_part']) {
                $idx['length'] = $index['Sub_part'];
            }
            $field = $index['Column_name'];

            $result[$name]['fields'][$field] = $idx;
        }

        return $result;
    }

    public static function table($conn, string $table): ?array
    {
        $sql = "SHOW CREATE TABLE `$table`;";
        $result = $conn->query($sql);
        if (!$result) {
            return null;
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $row  = $rows[0];
        $result->free();

        $result = [];

        $create_syntax = $row['Create Table'];
        $create_syntax = explode(PHP_EOL, $create_syntax);
        array_shift($create_syntax);
        array_pop($create_syntax);

        array_walk($create_syntax, function (&$line) {
            $line = trim($line, ' ,');
        });

        $primary_keys = [];
        $unique_keys = [];

        $regexs = [
            'comment' => '!COMMENT \'(?<cmn>[^\' ]+)\'!',
            'default' => '!DEFAULT \'?(?<def>[^\' ]+)\'?!',
            'field'   => '!^`(?<name>[^`]+)` (?<type>[a-z]+)(\((?<length>[^)]+)\))?.+?$!',
            'primary' => '!^PRIMARY KEY \(([^\)]+)\)$!',
            'unique'  => '!^UNIQUE KEY `[^`]+` \(`(?<field>[^`]+)`\)$!',
            'update'  => '!ON UPDATE \'?(?<update>[^\' ]+)\'?!'
        ];

        $no_length  = ['BIGINT','MEDIUMINT','INTEGER','SMALLINT','TINYINT'];
        $stamp_vars = ['current_timestamp()', 'current_timestamp'];

        foreach ($create_syntax as $fld) {
            if (preg_match($regexs['unique'], $fld, $match)) {
                $unique_keys[] = $match['field'];
            } elseif (preg_match($regexs['primary'], $fld, $match)) {
                $keys = explode(',', $match[1]);
                array_walk($keys, function (&$text) {
                    $text = trim($text, '` ');
                });
                $primary_keys = $keys;
            } elseif (preg_match($regexs['field'], $fld, $match)) {
                $name = $match['name'];
                $field = [
                    'comment' => '',
                    'name'    => $name,
                    'type'    => null,
                    'attrs'   => [
                        'length'    => null,
                        'options'   => [],
                        'null'      => true,
                        'unique'    => false,
                        'unsigned'  => false,
                        'default'   => null,
                        'update'    => null,
                        'primary_key' => false,
                        'auto_increment' => false
                    ]
                ];

                // type
                $type = strtoupper($match['type']);
                if ($type === 'INT') {
                    $type = 'INTEGER';
                }
                $field['type'] = $type;

                // length / options
                if (isset($match['length'])) {
                    $length = $match['length'];

                    if (in_array($type, ['SET', 'ENUM'])) {
                        $options = explode(',', $length);
                        array_walk($options, function (&$opt) {
                            $opt = trim($opt, " '");
                        });
                        $field['attrs']['options'] = $options;
                    } elseif (!in_array($type, $no_length)) {
                        $field['attrs']['length'] = $length;
                    }
                }

                // auto_increment
                if (false !== strstr($fld, 'AUTO_INCREMENT')) {
                    $field['attrs']['auto_increment'] = true;
                }

                // not null
                if (false !== strstr($fld, 'NOT NULL')) {
                    $field['attrs']['null'] = false;
                }

                // unsigned
                if (false !== strstr($fld, 'unsigned')) {
                    $field['attrs']['unsigned'] = true;
                }

                // default
                if (preg_match($regexs['default'], $fld, $def)) {
                    $def = $def['def'];
                    if ($def === 'NULL') {
                        $def = null;
                    }

                    if (in_array($def, $stamp_vars)) {
                        $def = 'CURRENT_TIMESTAMP';
                    }

                    $field['attrs']['default'] = $def;
                }

                // comment
                if (preg_match($regexs['comment'], $fld, $cmn)) {
                    $cmn = $cmn['cmn'];
                    $field['comment'] = $cmn;
                }

                // update
                if (preg_match($regexs['update'], $fld, $def)) {
                    $def = $def['update'];
                    if ($def === 'NULL') {
                        $def = null;
                    }

                    if (in_array($def, $stamp_vars)) {
                        $def = 'CURRENT_TIMESTAMP';
                    }

                    $field['attrs']['update'] = $def;
                }

                $result[$name] = $field;
            }
        }

        foreach ($primary_keys as $key) {
            $result[$key]['attrs']['primary_key'] = true;
        }

        foreach ($unique_keys as $key) {
            $result[$key]['attrs']['unique'] = true;
        }

        return $result;
    }
}
