<?php

namespace Mage\Mage\Core;

use Magento\Framework\App\CacheInterface;

trait Cache
{
    public static $cacheInterface;

    public static function save($data, $key, $tags = [], $ttl = 3600)
    {
        $cache = \Mage::get(CacheInterface::class);
        $cache->save(
            $data,
            $key,
            $tags,
            $ttl
        );
        //Instance of cache you can do whatever you wand after 
        return $cache;
    }

    public static function load($key)
    {
        $cache = \Mage::get(CacheInterface::class);
        $cache->load(
            $key
        );
        //Instance of cache you can do whatever you wand after 
        return $cache;
    }

    public static function delete($key)
    {
        $cache = \Mage::get(CacheInterface::class);
        $cache->remove(
            $key
        );
        //Instance of cache you can do whatever you wand after 
        return $cache;
    }

    public static function empty(){

    }

    public static function test(){
        $stat = microtime(true);
        for ($i = 1000000; $i !== 0 ; $i--){
            if(!isset(self::$cacheInterface))
            self::$cacheInterface = self::empty();
        }
        $end = microtime(true);
        echo "Execution Time: " . ($end - $stat) . PHP_EOL;
    }
}

Cache::test();

