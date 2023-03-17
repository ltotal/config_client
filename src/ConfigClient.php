<?php
namespace Ltotal\ConfigClient;

use Hprose\Socket\Client;
use Exception;

class ConfigClient
{
    protected static $checkUpdateInterval = 30;

    protected static $lockMaxExpired = 30;

    protected static $rpcServer;

    protected static $rpcTimeout = 1000;

    protected static $appId = '';

    protected static $appSecret = '';

    protected static $cluster = '';

    protected static $cacheFilePath = '';

    /**
     * 初始化
     * @param array $appConf
     * @return void
     */
    public static function init(array $appConf)
    {
        self::$rpcServer = self::getArrayValueByKey($appConf, 'rpc_server');
        self::$appId = self::getArrayValueByKey($appConf, 'app_id');
        self::$appSecret = self::getArrayValueByKey($appConf, 'app_secret');
        self::$cluster = self::getArrayValueByKey($appConf, 'cluster', 'default');
        self::$cacheFilePath = self::getArrayValueByKey($appConf, 'cache_file_path', './apollo');
    }

    public static function get(string $key = '')
    {
        self::checkAndWriteCache();
        $data = json_decode(self::getJsonConfigCache(), true);
        if(empty($data)) $data = [];
        return empty($key)? $data : $data[$key]?? [];
    }

    /**
     * 检查并写入本地配置缓存及本地配置最后更新时间
     * @return bool
     */
    protected static function checkAndWriteCache(): bool
    {
        $curTs = time();
        $haveWrite = false;
        $localLastUpdateTs = self::getLocalLastUpdateTs();

        if(!$localLastUpdateTs || ($curTs - $localLastUpdateTs >= self::$checkUpdateInterval)) {
            if(!self::checkWriteLockExist()) {
                self::addWriteLock();
                try {
                    $client = Client::create(self::$rpcServer, false);
                    $client->setTimeout(self::$rpcTimeout);
                    $res = $client->GetNameSpaceConfigData(
                        self::$cluster,
                        self::$appId,
                        self::$appSecret,
                        '',
                        '',
                        $localLastUpdateTs
                    );
                    $writeDataStatus = false;
                    $resData = json_decode($res, true);
                    $code = self::getArrayValueByKey($resData, 'code', 0);
                    if ($code == 200) {
                        $writeData = self::getArrayValueByKey($resData, 'data', []);
                        if (!empty($writeData)) {
                            $writeDataStatus = self::writeJsonConfigCache(
                                json_encode($writeData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                            );
                        }
                    }
                    //不管本次有无更新本地缓存配置，统一更新本地最后检查时间
                    $writeLastUpdateTsStatus = self::writeLocalLastUpdateTs($curTs);
                    $haveWrite = $writeDataStatus && $writeLastUpdateTsStatus;
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
                self::removeWriteLock();
            }
        }
        return $haveWrite;
    }

    protected static function checkWriteLockExist(): bool
    {
        $filePath = self::getWriteLockPath();
        if(!file_exists($filePath)) return false;
        $writeLock = trim(file_get_contents($filePath)) * 1;
        return $writeLock > 0 && (time() - $writeLock <= self::$lockMaxExpired);
    }

    /**
     * 添加本地写文件锁
     * @return bool
     */
    protected static function addWriteLock(): bool
    {
        $filePath = self::getWriteLockPath();
        $status = file_put_contents($filePath, time());
        return !($status === false);
    }

    /**
     * 移除本地写文件锁
     * @return bool
     */
    protected static function removeWriteLock(): bool
    {
        $filePath = self::getWriteLockPath();
        $status = file_put_contents($filePath, 0);
        return !($status === false);
    }

    protected static function getWriteLockPath(): string
    {
        return self::$cacheFilePath . '/lock';
    }

    /**
     * 写入本地缓存配置
     * @param string $data
     * @return bool
     */
    protected static function writeJsonConfigCache(string $data): bool
    {
        $filePath = self::getJsonConfigCachePath();
        $status = file_put_contents($filePath, $data);
        return !($status === false);
    }

    /**
     * 获取本地缓存配置
     * @return string
     */
    protected static function getJsonConfigCache(): string
    {
        $filePath = self::getJsonConfigCachePath();
        if($filePath) {
            return trim(file_get_contents($filePath));
        }
        return '';
    }

    /**
     * 获取本地缓存配置本地存储路径
     * @return string
     */
    protected static function getJsonConfigCachePath(): string
    {
        return self::$cacheFilePath . '/configs.json';
    }

    /**
     * 写入最后配置更新时间
     * @param string $data
     * @return string
     */
    protected static function writeLocalLastUpdateTs(string $data): string
    {
        $filePath = self::getLocalLastUpdateTsPath();
        $status = file_put_contents($filePath, $data);
        return !($status === false);
    }

    /**
     * 获取最后配置更新时间
     * @return int
     */
    protected static function getLocalLastUpdateTs(): int
    {
        $filePath = self::getLocalLastUpdateTsPath();
        if(file_exists($filePath)) {
            return trim(file_get_contents($filePath)) * 1;
        }
        return 0;
    }

    /**
     * 获取最后配置更新时间的本地存储路径
     * @return string
     */
    protected static function getLocalLastUpdateTsPath(): string
    {
        return self::$cacheFilePath . '/last_update_ts';
    }

    /**
     * 根据键名获取配置
     * @param array $data
     * @param string $key
     * @param mixed $default
     * @return mixed|string
     */
    protected static function getArrayValueByKey(array $data, string $key, $default = '')
    {
        return isset($data[$key]) && !empty($data[$key])? $data[$key] : $default;
    }
}
