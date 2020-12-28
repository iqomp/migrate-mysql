<?php

/**
 * Index comparer
 * @package iqomp/migrate-mysql
 * @version 1.0.0
 */

namespace Iqomp\MigrateMysql\Database;

use Iqomp\MigrateMysql\Config\Schema;

class Index
{
    public static function compare($conn, string $table, array $config): ?array
    {
        $tbl_indexes = Descriptor::index($conn, $table, $config['fields']);
        $cnf_indexes = Schema::index($config['indexes'], $config['fields']);

        if (!$tbl_indexes) {
            return ['index_create' => array_values($cnf_indexes)];
        }

        $result = [];

        // index delete
        foreach ($tbl_indexes as $name => $index) {
            if (isset($cnf_indexes[$name])) {
                continue;
            }

            if (!isset($result['index_delete'])) {
                $result['index_delete'][] = $index;
            }
        }

        // index create/update
        foreach ($cnf_indexes as $name => $cnf_index) {
            $tbl_index = $tbl_indexes[$name] ?? null;

            // create
            if (is_null($tbl_index)) {
                if (!isset($result['index_create'])) {
                    $result['index_create'] = [];
                }

                $result['index_create'][] = $cnf_index;
                continue;
            }

            $diff_found = false;

            // compare type
            if ($tbl_index['type'] != $cnf_index['type']) {
                $diff_found = true;
            }

            // compare fields
            if (!$diff_found) {
                $cnf_index_keys = array_keys($cnf_index['fields']);
                $tbl_index_keys = array_keys($tbl_index['fields']);
                if ($cnf_index_keys !== $tbl_index_keys) {
                    $diff_found = true;
                }
            }

            // compare length
            if (!$diff_found) {
                foreach ($cnf_index['fields'] as $name => $cnf_index_prop) {
                    $tbl_index_prop = $tbl_index['fields'][$name];
                    $tbl_index_len  = $tbl_index_prop['length'] ?? null;
                    $cnf_index_len  = $cnf_index_prop['length'] ?? null;

                    if ($cnf_index_len != $tbl_index_len) {
                        $diff_found = true;
                        break;
                    }
                }
            }

            if (!$diff_found) {
                continue;
            }

            if (!isset($result['index_update'])) {
                $result['index_update'] = [];
            }

            $result['index_update'][] = $cnf_index;
        }

        return $result;
    }
}
