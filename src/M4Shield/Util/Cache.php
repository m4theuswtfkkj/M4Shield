<?php

namespace M4Shield\Util;

class Cache
{
    private static $cache = [];

    public static function add($key, $value)
    {
        self::$cache[$key] = $value;
    }

    public static function remove($key)
    {
        unset(self::$cache[$key]);
    }

    public static function get($key)
    {
        return self::$cache[$key] ?? null;
    }

    public static function clearAll()
    {
        self::$cache = [];
    }
    
    public static function hasCache($key = "all")
    {
        if ($key === "all") {
            return isset(self::$cache);
        }

        return isset(self::$cache[$key]);
    }
}