<?php

/**
 * User provided config schema parser
 * @package iqomp/migrate-mysql
 * @version 1.0.0
 */

namespace Iqomp\MigrateMysql\Config;

class Schema
{
    protected static $ignore_length = [
        'BOOLEAN',
        'BIGINT',
        'MEDIUMINT',
        'INTEGER',
        'SMALLINT',
        'TINYINT'
    ];

    protected static $config_by_types = [
        'BIGINT' => [],

        'BOOLEAN' => [],

        'CHAR' => [
            'attrs' => [
                'length' => 1
            ]
        ],

        'DATE' => [],

        'DATETIME' => [],

        'DECIMAL' => [
            'attrs' => [
                'length' => '10,0'
            ]
        ],

        'DOUBLE' => [],

        'ENUM' => [],

        'FLOAT' => [],

        'INTEGER' => [],

        'LONGTEXT' => [],

        'MEDIUMINT' => [],

        'SET' => [],

        'SMALLINT' => [],

        'TEXT' => [],

        'TIMESTAMP' => [
            'attrs' => [
                'null' => false
            ]
        ],

        'TIME' => [],

        'TINYINT' => [],

        'TINYTEXT' => [],

        'VARCHAR' => [
            'attrs' => [
                'length' => 50
            ]
        ],

        'YEAR' => [
            'attrs' => [
                'length' => 4
            ]
        ],
    ];

    public static function index(array $indexes, array $fields): array
    {
        $default = [
            'type'   => 'BTREE',
            'fields' => []
        ];

        foreach ($indexes as $name => &$index) {
            $index['name'] = $name;
            $index = array_replace($default, $index);
        }
        unset($index);

        return $indexes;
    }

    public static function table(array $fields): array
    {
        $default = [
            'attrs' => [
                'length'   => null,
                'options'  => [],
                'null'     => true,
                'default'  => null,
                'update'   => null,
                'unsigned' => false,
                'unique'   => false,
                'primary_key' => false,
                'auto_increment' => false
            ]
        ];

        $config_by_types = self::$config_by_types;
        foreach ($config_by_types as $name => &$config) {
            $config = array_replace_recursive($default, $config);
        }
        unset($config);

        foreach ($fields as $name => $field) {
            $field['name'] = $name;
            $field['type'] = strtoupper($field['type']);
            if ($field['type'] === 'INT') {
                $field['type'] = 'INTEGER';
            }

            $type = $field['type'];

            if (!isset($config_by_types[$type])) {
                continue;
            }

            $default_config = $config_by_types[$type];
            $field = array_replace_recursive($default_config, $field);

            // PER TYPE FIXER

            // new version of mysql ignore this column type length
            if (in_array($type, self::$ignore_length)) {
                $field['attrs']['length'] = null;
            }

            // primary key should not accept null
            if ($field['attrs']['primary_key']) {
                $field['attrs']['null'] = false;
            }

            // tinyint is for boolean
            if ($type === 'BOOLEAN') {
                $field['type'] = 'TINYINT';
                if (!is_null($field['attrs']['default'])) {
                    $field['attrs']['default'] = (int)$field['attrs']['default'];
                }
            }

            // timestamp should be uppercase
            if (isset($field['attrs']['default'])) {
                if ($field['attrs']['default'] === 'current_timestamp') {
                    $field['attrs']['default'] = 'CURRENT_TIMESTAMP';
                }
            }

            if (isset($field['attrs']['update'])) {
                if ($field['attrs']['update'] === 'current_timestamp') {
                    $field['attrs']['update'] = 'CURRENT_TIMESTAMP';
                }
            }

            $fields[$name] = $field;
        }

        return $fields;
    }
}
