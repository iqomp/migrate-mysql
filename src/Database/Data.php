<?php

/**
 * Data seed provider
 * @package iqomp/migrate-mysql
 * @version 1.0.0
 */

namespace Iqomp\MigrateMysql\Database;

class Data
{
    public static function compare($conn, string $table, array $config): ?array
    {
        $result = [
            'data_create' => []
        ];

        foreach ($config['data'] as $field => $rows) {
            foreach ($rows as $v1 => $row) {
                if (!isset($row[$field])) {
                    $row[$field] = $v1;
                }

                $sql = "SELECT * FROM `$table` WHERE `$field` = '$v1';";
                $exists = $conn->query($sql);
                if ($exists) {
                    $exists = $exists->fetch_all(MYSQLI_ASSOC);
                }

                if (!$exists) {
                    $result['data_create'][] = $row;
                }
            }
        }

        if ($result['data_create']) {
            return $result;
        }

        return [];
    }
}
