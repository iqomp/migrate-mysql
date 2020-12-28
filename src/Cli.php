<?php

/**
 * Command line result printer
 * @package iqomp/migrate-mysql
 * @version 1.0.0
 */

namespace Iqomp\MigrateMysql;

use Symfony\Component\Console\Input\InputInterface as In;
use Symfony\Component\Console\Output\OutputInterface as Out;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;

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

    public static function diff(array $diff, In $in, Out $out): void
    {
        $io = new SymfonyStyle($in, $out);
        $io->title($diff['model'] . ' (' . $diff['table'] . ')');

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

        $table = new Table($out);
        $table->setHeaders(['Action', 'Name', 'Attributes']);
        $table->setRows($rows);
        $table->render();
    }
}
