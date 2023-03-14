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

    protected function initApp(array $appConf)
    {
        $appId = $this->getConfByKey($appConf, 'app_id', '');
        $cluster = $this->getConfByKey($appConf, 'cluster', 'default');
        $this->cacheFilePath = $this->getConfByKey($appConf, 'cache_file_path', './apollo');
        $nameSpaces = $this->getRedisCli()->get($cluster . '.' . $appId . '.namespaces');
        $nameSpaces = json_decode($nameSpaces, true);
        $localLastUpdateTs = $this->getLocalLastUpdateTs();
        $updateNess = empty($localLastUpdateTs);
        if(!empty($nameSpaces)) {
            $cacheKeys = [];
            $localLastUpdateTs *= 1;
            foreach ($nameSpaces as $nameSpace => $ts) {
                if(!$updateNess && ($ts * 1 >= $localLastUpdateTs)) {
                    $updateNess = true;
                }
                $cacheKeys[] = $this->getConfigCacheKey($appId, $nameSpace);
            }
            if($updateNess && !empty($cacheKeys)) {
                $final = [];
                $allCacheData = $this->getRedisCli()->mGet($cacheKeys);
                foreach ($cacheKeys as $k => $v) {
                    $tmp = json_decode($allCacheData[$k], true);
                    $final[$v] = $tmp['data'];
                }
                $this->writeJsonConfigCache(
                    json_encode($final, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
                );
                $this->writeLocalLastUpdateTs(time());
                print_r('Writing...');
            }
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
        return 'apollo_config.' . $appId . '.' . $namespace;
    }

    /**
     * @param string $data
     * @return bool
     */
    protected function writeJsonConfigCache(string $data): bool
    {
        $filePath = $this->cacheFilePath . '/configs.json';
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
     * @return int
     */
    protected function getLocalLastUpdateTs(): int
    {
        $filePath = $this->cacheFilePath . '/last_update_ts';
        if(file_exists($filePath)) {
            return trim(file_get_contents($filePath)) * 1;
        }
        return 0;
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
