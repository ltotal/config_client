<?php
namespace Ltotal\ConfigClient;

class ConfigClient
{
    protected $redisCli;

    protected $cacheFilePath = '';

    public function __construct(array $appConf, array $redisConf)
    {
        $this->initRedis($redisConf);
        $this->initApp($appConf);
    }

    /**
     * @param array $appConf
     * @return void
     */
    protected function initApp(array $appConf)
    {
        $apps = $this->getConfByKey($appConf, 'apps', []);
        $this->cacheFilePath = isset($appConf['cache_file_path']) && !empty($appConf['cache_file_path'])? $appConf['cache_file_path'] : './apollo';
        $this->cacheFilePath = $this->getConfByKey($appConf, 'cache_file_path', './apollo');

        if(!empty($apps)) {
            $allKeysConfMap = [];
            foreach ($apps as $conf) {
                $appId = $this->getConfByKey($conf, 'app_id');
                //$appSecret = $this->getConfByKey($conf, 'app_secret');
                $namespaces = $this->getConfByKey($conf, 'app_namespaces', []);
                if(!empty($appId) && !empty($namespaces)) {
                    foreach ($namespaces as $namespace) {
                        $allKeysConfMap[$appId . '.' . $namespace] = [
                            'app_id' => $appId,
                            'namespace' => $namespace,
                            'cache_key' => $this->getConfigCacheKey($appId, $namespace),
                        ];
                    }
                }
            }
            try {
                $final = [];
                $curTs = time();
                $localLastUpdateTs = $this->getLocalLastUpdateTs();
                $updateNess = empty($localLastUpdateTs);
                $cacheKeys = array_column($allKeysConfMap, 'cache_key');
                $allCacheData = $this->getRedisCli()->mGet($cacheKeys);
                if(!empty($allCacheData)) {
                    $idx = 0;
                    foreach ($allKeysConfMap as $k => $v) {
                        $tmp = json_decode($allCacheData[$idx], true);
                        if(empty($tmp)) {
                            $final = [];
                            break;
                        }
                        $final[$k] = $tmp['data'];
                        if(!$updateNess && $tmp['last_update_ts'] * 1 >= $localLastUpdateTs * 1) {
                            $updateNess = true;
                        }
                        $idx += 1;
                    }
                }
                if($updateNess && !empty($final)) {
                    $this->writeJsonConfigCache(
                        json_encode($final, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
                    );
                    $this->writeLocalLastUpdateTs($curTs);
                }
            } catch (\RedisException $e) {}
        }
    }

    /**
     * @param array $redisConf
     * @return void
     */
    protected function initRedis(array $redisConf = [])
    {
        $redisCli = $this->getConfByKey($redisConf, 'redis_client', null);
        if($redisCli !== null) {
            $this->setRedisCli($redisCli);
        } else if(!empty($redisConf)) {
            try {
                $redisCli = new \Redis();
                list($host, $port, $auth) = [
                    $this->getConfByKey($redisConf, 'host', '127.0.0.1'),
                    $this->getConfByKey($redisConf, 'port', 6379),
                    $this->getConfByKey($redisConf, 'auth')
                ];
                $redisCli->connect($host, $port, 0.5);
                $redisCli->auth($auth);
                $this->setRedisCli($redisCli);
            } catch (\RedisException $e) {}
        }
    }

    /**
     * @param $redisCli
     * @return void
     */
    protected function setRedisCli($redisCli)
    {
        $this->redisCli = $redisCli;
    }

    /**
     * @return mixed
     */
    protected function getRedisCli()
    {
        return $this->redisCli;
    }

    /**
     * @param $appId
     * @param $namespace
     * @return string
     */
    protected function getConfigCacheKey($appId, $namespace): string
    {
        return 'apollo_config_' . $appId . '_' . $namespace;
    }

    /**
     * @param string $data
     * @return bool
     */
    protected function writeJsonConfigCache(string $data): bool
    {
        $filePath = $this->cacheFilePath . '/configs.json';
        print_r($filePath);
        $status = file_put_contents($filePath, $data);
        return !($status === false);
    }

    /**
     * @param string $data
     * @return string
     */
    protected function writeLocalLastUpdateTs(string $data): string
    {
        $filePath = $this->cacheFilePath . '/last_update_ts';
        $status = file_put_contents($filePath, $data);
        return !($status === false);
    }

    /**
     * @return string
     */
    protected function getLocalLastUpdateTs(): string
    {
        $filePath = $this->cacheFilePath . '/last_update_ts';
        if(file_exists($filePath)) {
            return trim(file_get_contents($filePath));
        }
        return '';
    }

    /**
     * @param array $data
     * @param string $key
     * @param mixed $default
     * @return mixed|string
     */
    protected function getConfByKey(array $data, string $key, $default = '')
    {
        return isset($data[$key]) && !empty($data[$key])? $data[$key] : $default;
    }
}
