<?php

/**
 * Command line result printer
 * @package iqomp/migrate-mysql
 * @version 2.0.0
 */

namespace Iqomp\MigrateMysql;

use Hyperf\Command\Command as HyperfCommand;

class Cli
{
    protected static function makeDataLabel(array $data): string
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[] = $key . ':' . $value;
        }

        $result = implode(',', $result);

        return substr($result, 0, 15) . '...';
    }

    public static function diff(array $diff, HyperfCommand $cli): void
    {
        $cli->info($diff['model'] . ' (' . $diff['table'] . ')');

        $result = $diff['result'];

        $rows = [];
        foreach ($result as $action => $options) {
            switch ($action) {
                case 'data_create':
                    $row = [$action, ''];
                    foreach ($options as $line) {
                        $label = self::makeDataLabel($line);

                        if ($row) {
                            $row[] = $label;
                        } else {
                            $row = ['', '', $label];
                        }

                        $rows[] = $row;
                        $row = [];
                    }
                    break;

                case 'field_create':
                case 'field_update':
                    $row = [$action];
                    foreach ($options as $info) {
                        $fname = $info['name'];
                        if ($row) {
                            $row[] = $fname;
                            $row[] = $info['type'];
                        } else {
                            $row = ['', $fname, $info['type']];
                        }

                        $rows[] = $row;
                        $row = [];
                    }
                    break;

                case 'field_delete':
                    $row = ['field_delete'];
                    foreach ($options as $fname) {
                        if ($row) {
                            $row[] = $fname;
                            $row[] = '';
                        } else {
                            $row = ['', $fname, ''];
                        }

                        $rows[] = $row;
                        $row = [];
                    }
                    break;

                case 'index_create':
                case 'index_update':
                    foreach ($options as $info) {
                        $iname = $info['name'];
                        $itype = $info['type'];

                        $rows[] = [$action, $iname, $itype];

                        foreach ($info['fields'] as $fname => $iopt) {
                            $ilabel = $fname;
                            if (isset($iopt['length'])) {
                                $ilabel .= '(' . $iopt['length'] . ')';
                            }
                            $rows[] = ['', '', $ilabel];
                        }
                    }
                    break;

                case 'index_delete':
                    $row = [$action];
                    foreach ($options as $info) {
                        $iname = $info['name'];
                        if ($row) {
                            $row[] = $iname;
                            $row[] = '';
                        } else {
                            $row = ['', $iname, ''];
                        }

                        $rows[] = $row;
                        $row = [];
                    }
                    break;

                case 'table_create':
                    $row = ['table_create', $diff['table']];
                    foreach ($options as $info) {
                        $fname = $info['name'];
                        $col_type = $fname  . ' ' . $info['type'];
                        if ($row) {
                            $row[] = $col_type;
                        } else {
                            $row = ['','',$col_type];
                        }

                        $rows[] = $row;
                        $row = [];
                    }
                    break;

                default:
                    $rows[] = [$action, '-', 'Unknow action name'];
            }
        }

        $cli->table(['Action', 'Name', 'Attributes'], $rows);
    }
}
