<?php

/**
 * Helper class that bring various helper function
 * @package iqomp/migrate-mysql
 * @version 1.0.0
 */

namespace Iqomp\MigrateMysql;

class Helper
{
    public static function arrayFlatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $val) {
            $c_prefix = $prefix . $key;
            if (is_array($val) || is_object($val)) {
                $val = (array)$val;
                if (self::isIndexedArray($val)) {
                    if (!$val) {
                        $result[$c_prefix] = '';
                    } elseif (is_object($val[0]) || is_array($val[0])) {
                        $res = self::arrayFlatten($val, $c_prefix . '.');
                        $result = array_merge($result, $res);
                    } else {
                        $result[$c_prefix] = implode(', ', $val);
                    }
                } else {
                    $res = self::arrayFlatten($val, $c_prefix . '.');
                    $result = array_merge($result, $res);
                }
            } else {
                $result[$c_prefix] = $val;
            }
        }

        return $result;
    }

    public static function isIndexedArray(array $array): bool
    {
        if (!$array) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }
}
