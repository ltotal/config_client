<?php
ini_set('display_errors', 'on');
error_reporting(E_ALL);

require_once './vendor/autoload.php';

use Ltotal\ConfigClient;

$appConf = [
    'apps' => [
        [
            'app_id' => 'sales-service',
            'app_namespaces' => ['SALES.moor_call', 'SALES.ppw_cs_api'],
            'app_secret' => '',
        ],
        [
            'app_id' => 'sales-db',
            'app_namespaces' => ['SALES.rz_hfdb_core', 'SALES.rz_mktup'],
            'app_secret' => '',
        ],
    ],
    'cache_file_path' => '',
];

$redisConf = [
    'host' => '127.0.0.1',
    'port' => 6379,
    'auth' => '',
];
$cls = new ConfigClient\ConfigClient($appConf, $redisConf);
