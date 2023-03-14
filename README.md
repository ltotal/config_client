# ConfigClient

## 使用

- $appConf 应用程序配置：<br>
app_id：配置中心设置的应用id<br>
app_secret: 配置中心的应用密钥<br>
cluster：配置中心使用的集群


- $redisConf redis相关配置


具体例子

```php
$appConf = [
'app_id' => 'sales-mkt',
'app_secret' => '',
'cluster' => 'default',
'cache_file_path' => './apollo',
];

$redisConf = [
'host' => '127.0.0.1',
'port' => 6379,
'auth' => '',
'redis_client' => '',
];

ConfigClient::getInstance()->init($appConf, $redisConf);
$data = ConfigClient::getInstance()->get('SALES.rz_hfdb_core');
```
