<?php

/**
 * MySQL migrator
 * @package iqomp/migrate-mysql
 * @version 2.0.0
 */

namespace Iqomp\MigrateMysql;

use Iqomp\MigrateMysql\Database\{
    Table,
    Index,
    Data
};

class Syncer
{
    public static function compare($conn, string $model, string $table, array $config): ?array
    {
        if (!isset($config['fields'])) {
            return null;
        }

        $final = [
            'model'  => $model,
            'table'  => $table,
            'config' => $config,
            'result' => []
        ];

        $result = [];

        $fields = Table::compare($conn, $table, $config);
        if ($fields) {
            $result = array_replace($result, $fields);
        }

        if (isset($config['indexes'])) {
            $index = Index::compare($conn, $table, $config);
            if ($index) {
                $result = array_replace($result, $index);
            }
        }

        if (isset($config['data'])) {
            $data = Data::compare($conn, $table, $config);
            if ($data) {
                $result = array_replace($result, $data);
            }
        }

        $final['result'] = $result;

        return $final;
    }
}
