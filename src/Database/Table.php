<?php

/**
 * Table real to config comparition
 * @package iqomp/migrate-mysql
 * @version 1.0.0
 */

namespace Iqomp\MigrateMysql\Database;

use Iqomp\MigrateMysql\Helper;
use Iqomp\MigrateMysql\Config\Schema;

class Table
{
    public static function compare($conn, string $table, array $config): ?array
    {
        $tbl_fields = Descriptor::table($conn, $table);
        $cnf_fields = Schema::table($config['fields']);

        if (!$tbl_fields) {
            return ['table_create' => array_values($cnf_fields)];
        }

        $tbl_fields_idx = array_values($tbl_fields);
        $tbl_fields_by_name = [];

        foreach ($tbl_fields_idx as $index => $field) {
            $field_name = $field['name'];
            $tbl_fields_by_name[$field_name] = $index;
        }

        $cnf_fields_idx = array_values($cnf_fields);

        $result = [];

        // remove column
        foreach ($tbl_fields as $name => $field) {
            if (isset($cnf_fields[$name])) {
                continue;
            }

            if (!isset($result['field_delete'])) {
                $result['field_delete'] = [];
            }

            $result['field_delete'][] = $name;
            $index = $tbl_fields_by_name[$name];
            unset($tbl_fields_idx[$index]);
        }

        $tbl_fields_idx = array_values($tbl_fields_idx);

        // create and update column
        foreach ($cnf_fields_idx as $index => $field) {
            $field_name = $field['name'];

            // create
            if (!isset($tbl_fields[$field_name])) {
                if (!isset($result['field_create'])) {
                    $result['field_create'] = [];
                }

                $result['field_create'][] = $field;

                array_splice($tbl_fields_idx, $index, 0, [$field]);
                continue;
            }

            // compare column attributes
            $tbl_field = $tbl_fields[$field_name];
            $tbl_field_flat = Helper::arrayFlatten($tbl_field);
            $cnf_field_flat = Helper::arrayFlatten($field);

            foreach ($tbl_field_flat as $name => $val) {
                $cnf_field_val = $cnf_field_flat[$name] ?? null;
                if ($cnf_field_val == $val) {
                    continue;
                }

                if (!isset($result['field_update'])) {
                    $result['field_update'] = [];
                }

                $result['field_update'][] = $field;

                continue 2;
            }

            // compare column index
            if (isset($tbl_fields_idx[$index])) {
                continue;
            }

            $tbl_field_by_index = $tbl_fields_idx[$index];

            if ($tbl_field_by_index['name'] == $field['name']) {
                continue;
            }

            if (!isset($result['field_update'])) {
                $result['field_update'] = [];
            }

            $result['field_update'][] = $field;
        }

        if (!$result) {
            return null;
        }

        return $result;
    }
}
