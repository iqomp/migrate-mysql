<?php

/**
 * Query builder interface
 * @package iqomp/migarte-mysql
 * @version 1.0.0
 */

namespace Iqomp\MigrateMysql\Database;

interface QueryBuilderInterface
{
    public static function setOptions(array $options): void;
}
