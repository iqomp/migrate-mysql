<?php

/**
 * Config provider
 * @package iqomp/migrate-mysql
 * @version 2.0.0
 */

namespace Iqomp\MigrateMysql;

class ConfigProvider
{
    public function __invoke()
    {
        return [
            'model' => [
                'migrators' => [
                    'mysql' => 'Iqomp\\MigrateMysql\\Migrator'
                ]
            ]
        ];
    }
}
