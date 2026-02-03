<?php

return
[
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => 'localhost',
            'name' => 'u967889760_fastpayment',
            'user' => 'u967889760_fast',
            'pass' => 'Mistura#1',
            'port' => '3306',
            'charset' => 'utf8',
        ],
        'production' => [
            'adapter' => 'mysql',
            'host' => 'localhost',
            'name' => 'u967889760_fastpayment',
            'user' => 'u967889760_fast',
            'pass' => 'Mistura#1',
            'port' => '3306',
            'charset' => 'utf8',
        ],
    ],
    'version_order' => 'creation'
];
