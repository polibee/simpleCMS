<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 备份目录
    |--------------------------------------------------------------------------
    |
    | 存放 .sql 备份与临时凭据文件的位置。默认 storage/app/backups，
    | 该目录不在 public 下，Web 无法直接访问。
    |
    */

    'disk_dir' => env('BACKUP_DIR', storage_path('app/backups')),

    /*
    |--------------------------------------------------------------------------
    | mysqldump / mysql 可执行文件
    |--------------------------------------------------------------------------
    |
    | 优先使用 binaries 中显式配置的绝对路径；未配置时按 search_paths 里的
    | 通配符模式查找（{tool} 会被替换为 mysqldump / mysql）；都找不到则
    | 回退到 PATH 中的同名命令。
    |
    | 原先硬编码的本机 Laragon mysql bin 通配路径仅适用于开发机，已改为可配置。
    | Windows 示例：'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe'
    |
    */

    'binaries' => [
        'mysqldump' => env('BACKUP_MYSQLDUMP_PATH'),
        'mysql' => env('BACKUP_MYSQL_PATH'),
    ],

    'search_paths' => array_values(array_filter([
        env('BACKUP_MYSQL_BIN_GLOB'),
    ])),

];
