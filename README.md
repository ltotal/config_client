# ConfigClient

## 安装
```composer
composer require ltotal/config_client
```

## 使用

```php
require_once './vendor/autoload.php';

use Ltotal\ConfigClient\ConfigClient;

$appConf = [
    'app_id' => 'sales-mkt', // 配置中心设置的应用id
    'app_secret' => '', // 配置中心的应用密钥
    'cluster' => 'default', // 配置中心使用的集群
    'cache_file_path' => './apollo' // 本地配置缓存路径
];

$redisConf = [
    'host' => '127.0.0.1',
    'port' => 6379,
    'auth' => '',
    'redis_client' => ''
];

ConfigClient::getInstance()->init($appConf, $redisConf);
$data = ConfigClient::getInstance()->get('SALES.rz_hfdb_core');
```
