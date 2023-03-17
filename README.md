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
    'rpc_server' => 'tcp://127.0.0.1:1234', // 配置中心客户端服务
    'app_id' => 'sales-mkt', // 配置中心设置的应用id
    'app_secret' => '', // 配置中心的应用密钥
    'cluster' => 'default', // 配置中心使用的集群
    'cache_file_path' => './apollo', // 本地配置缓存路径
];

ConfigClient::init($appConf);
$data = ConfigClient::get('SALES.rz_hfdb_core');
```
