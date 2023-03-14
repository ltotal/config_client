<?php
namespace Ltotal\ConfigClient;

use Redis;
use RedisException;

class ConfigClient
{
    private static $_client;

    protected $redisCli;

    protected $appId = '';

    protected $appSecret = '';

    protected $cluster = '';

    protected $cacheFilePath = '';

    protected function __construct()
    {}

    /**
     * @return ConfigClient
     */
    public static function getInstance(): ConfigClient
    {
        if (empty(self::$_client)) {
            self::$_client = new ConfigClient();
        }
        return self::$_client;
    }

    /**
     * 初始化
     * @param array $appConf
     * @param array $redisConf
     * @return void
     */
    public function init(array $appConf, array $redisConf)
    {
        $this->initRedis($redisConf);
        $this->initApp($appConf);
    }

    /**
     * 根据键名获取(全部/特定)配置
     * @param string $key
     * @return array
     */
    public function get(string $key = ''): array
    {
        $data = [];
        $localLastUpdateTs = $this->getLocalLastUpdateTs();
        $curTs = time();
        if(!$localLastUpdateTs || ($curTs - $localLastUpdateTs >= 60)) {
            $nameSpaces = $this->getRedisCli()->get($this->cluster . '.' . $this->appId . '.namespaces');
            $nameSpaces = json_decode($nameSpaces, true);
            if (!empty($nameSpaces)) {
                $cacheKeys = [];
                $updateNess = empty($localLastUpdateTs);
                foreach ($nameSpaces as $nameSpace => $ts) {
                    if (!$updateNess && ($ts * 1 >= $localLastUpdateTs)) {
                        $updateNess = true;
                    }
                    $cacheKeys[] = $this->getConfigCacheKey($this->appId, $nameSpace);
                }
                if (!empty($cacheKeys)) {
                    $allCacheData = $this->getRedisCli()->mGet($cacheKeys);
                    if (!empty($allCacheData)) {
                        foreach ($cacheKeys as $k => $v) {
                            $tmp = json_decode($allCacheData[$k], true);
                            $data[$v] = $tmp['data'];
                        }
                        $this->checkAndWriteCache($data, $updateNess, $curTs);
                    }
                }
            }
        }else {
            $data = json_decode($this->getJsonConfigCache(), true);
            if($data === false) $data = [];
        }
        if(empty($key)) {
            return $data;
        }
        $key = $this->getConfigCacheKey($this->appId, $key);
        return $data[$key]?? [];
    }

    /**
     * 检查并写入本地配置缓存及本地配置最后更新时间
     * @param array $writeData
     * @param bool $updateNess
     * @param int $curTs
     * @return void
     */
    protected function checkAndWriteCache(array $writeData, bool $updateNess, int $curTs)
    {
        if($updateNess) {
            $this->writeJsonConfigCache(
                json_encode($writeData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
            $this->writeLocalLastUpdateTs($curTs);
        }
    }

    /**
     * 初始化app
     * @param array $appConf
     * @return void
     */
    protected function initApp(array $appConf)
    {
        $this->appId = $this->getConfByKey($appConf, 'app_id');
        $this->appSecret = $this->getConfByKey($appConf, 'app_secret');
        $this->cluster = $this->getConfByKey($appConf, 'cluster', 'default');
        $this->cacheFilePath = $this->getConfByKey($appConf, 'cache_file_path', './apollo');
    }

    /**
     * 初始化redis
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
                $redisCli = new Redis();
                list($host, $port, $auth) = [
                    $this->getConfByKey($redisConf, 'host', '127.0.0.1'),
                    $this->getConfByKey($redisConf, 'port', 6379),
                    $this->getConfByKey($redisConf, 'auth')
                ];
                $redisCli->connect($host, $port, 0.5);
                $redisCli->auth($auth);
                $this->setRedisCli($redisCli);
            } catch (RedisException $e) {}
        }
    }

    /**
     * 设置redis实例
     * @param $redisCli
     * @return void
     */
    protected function setRedisCli($redisCli)
    {
        $this->redisCli = $redisCli;
    }

    /**
     * 获取redis实例
     * @return mixed
     */
    protected function getRedisCli()
    {
        return $this->redisCli;
    }

    /**
     * 获取本地缓存配置对应的redis键名
     * @param $appId
     * @param $namespace
     * @return string
     */
    protected function getConfigCacheKey($appId, $namespace): string
    {
        return 'apollo_config.' . $appId . '.' . $namespace;
    }

    /**
     * 写入本地缓存配置
     * @param string $data
     * @return bool
     */
    protected function writeJsonConfigCache(string $data): bool
    {
        $filePath = $this->getJsonConfigCachePath();
        $status = file_put_contents($filePath, $data);
        return !($status === false);
    }

    /**
     * 获取本地缓存配置
     * @return string
     */
    protected function getJsonConfigCache(): string
    {
        $filePath = $this->getJsonConfigCachePath();
        if($filePath) {
            return trim(file_get_contents($filePath));
        }
        return '';
    }

    /**
     * 获取本地缓存配置本地存储路径
     * @return string
     */
    protected function getJsonConfigCachePath(): string
    {
        return $this->cacheFilePath . '/configs.json';
    }

    /**
     * 写入最后配置更新时间
     * @param string $data
     * @return string
     */
    protected function writeLocalLastUpdateTs(string $data): string
    {
        $filePath = $this->getLocalLastUpdateTsPath();
        $status = file_put_contents($filePath, $data);
        return !($status === false);
    }

    /**
     * 获取最后配置更新时间
     * @return int
     */
    protected function getLocalLastUpdateTs(): int
    {
        $filePath = $this->getLocalLastUpdateTsPath();
        if(file_exists($filePath)) {
            return trim(file_get_contents($filePath)) * 1;
        }
        return 0;
    }

    /**
     * 获取最后配置更新时间的本地存储路径
     * @return int
     */
    protected function getLocalLastUpdateTsPath(): string
    {
        return $this->cacheFilePath . '/last_update_ts';
    }

    /**
     * 根据键名获取配置
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
