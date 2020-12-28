<?php

/**
 * Build query based on diff config to db result.
 * @package iqomp/migrate-mysql
 * @version 1.0.0
 */

namespace Iqomp\MigrateMysql\Database;

class QueryBuilder
{
    public static function build(array $options, &$conn): array
    {
        $result   = [];
        $table    = $options['table'];
        $diffs    = $options['result'];
        $actions  = [
            // this should be called sequentially
            'table_create' => ['QueryTable',  'create'],   // 1.
            'field_delete' => ['QueryColumn', 'remove'],   // 2.
            'field_create' => ['QueryColumn', 'create'],   // 3.
            'field_update' => ['QueryColumn', 'update'],   // 4.
            'index_delete' => ['QueryIndex',  'remove'],   // 5.
            'index_create' => ['QueryIndex',  'create'],   // 6.
            'index_update' => ['QueryIndex',  'update'],   // 7.
            'data_create'  => ['QueryData',   'create']    // 8.
        ];

        foreach ($actions as $key => $handler) {
            if (!isset($diffs[$key]) || !$diffs[$key]) {
                continue;
            }

            $handler[0] = 'Iqomp\\MigrateMysql\\Database\\' . $handler[0];

            $class  = $handler[0];
            $method = $handler[1];
            $fields = $diffs[$key];

            $class::setOptions($options);
            $res = $class::$method($table, $fields, $conn);
            $result = array_merge($result, $res);
        }

        return $result;
    }
}
