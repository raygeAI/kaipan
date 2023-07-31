<?php

defined('IN_IA') or exit('Access Denied');
define('MEMCACHE', @extension_loaded('Memcached'));

function cache()
{
    static $_cache;
    if (empty($_cache)) {
        if (MEMCACHE) {
            $_cache = new Memcached();
            if(!$_cache->addServer("localhost", 11211)){
                throw new Exception('无法连接Memcache');
            }
        }else{
            $_cache = new Memcache();
            if(!$_cache->connect('localhost', 11211))
            {
                throw new Exception('无法连接Memcache');
            }
        }
    }
    return $_cache;
}

function memcached_addKey($key,$expr=120,$retry=6){
    do{
        if(MEMCACHE) {
            $result = cache()->add($key, null, $expr);
        }else{
            $result = cache()->add($key, null,0, $expr);
        }
        usleep(10);
        $retry--;
    }while((!$result)&&($retry>=0));
    return $result;
}

function memcached_get($key, $namespace = '')
{
    return cache()->get($key);
}

function memcached_set($key, $data, $expiration=null)
{
    if(MEMCACHE) {
        return cache()->set($key, $data, $expiration);
    }else{
        return cache()->set($key, $data, 0,$expiration);
    }
}

function memcached_delete($key, $namespace = '')
{
    cache()->delete($key);
}

function memcached_clean($namespace = '')
{
    if(empty($namespace)) {
        cache()->flush();
    }else{
        $keys=cache()->getAllKeys();
        foreach($keys as $key){
            if(strpos($key,$namespace)===0){
                cache()->delete($key); 
            }
        }
    }
}


