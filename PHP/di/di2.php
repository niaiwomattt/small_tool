<?php

interface DiAwareInterface
{
    public function setDI();
    public function getDI();
}

class Di
{
    protected $_service = [];

    public function set($name, $definition)
    {
        $this->_service[$name] = $definition;
    }

    public function get($name)
    {
        if (isset($this->_service[$name])) {
            $definition = $this->_service[$name];
        }else {
            throw new Exception("Service '".$name."' wasn't found in the dependency injection", 1);

        }

        if (is_object($definition)) {
            $instance = call_user_func($definition);
        }

        // 如果实现了DiAwareInterface这个接口，自动注入
        if (is_object($instance)) {
            if ($instance instanceof DiAwareInterface) {
                $instance->setDI($this);
            }
        }

        return $instance;
    }

}

class Cache
{
    protected $_di;
    protected $_options;
    protected $_connect;

    public function __construct($options = null)
    {
        $this->_options = $options;
    }

    public function setDI($di)
    {
        $this->_di = $di;
    }

    protected function _connect()
    {
        $options = $this->_options;
        if (isset($options['connect'])) {
            $service = $options['connect'];
        }else {
            $service = 'redis';
        }

        return $this->_di->get($service);
    }

    public function get($key, $lifetime = 0)
    {
        $connect = $this->_connect;
        if (!is_object($connect)) {
            $connect = $this->_connect();
            $this->_connect = $connect;
        }
        // code...
        return $connect->find($key, $lifetime);
    }

    public function save($key = '', $value = '', $lifetime = 0)
    {
        $connect = $this->_connect;
        if (!is_object($connect)) {
            $connect = $this->_connect();
            $this->_connect = $connect;
        }
        // code...
        return $connect->save($key, $value, $lifetime);
    }

    public function delete($key)
    {
        $connect = $this->_connect;
        if (!is_object($connect)) {
            $connect = $this->_connect();
            $this->_connect = $connect;
        }
        // code...
        $connect->delete($key);
    }
}

 class RedisDB implements BackendInterface,DiAwareInterface
 {
    protected $_di;
    protected $_options;

    public function __construct($options = null)
    {
        $this->_options = $options;
    }

    public function setDI($di)
    {
        $this->_di  = $di;
    }

    public function find($key,$lifetime)
    {
        echo 'find'."\n";
    }

    public function save($key,$val,$lifetime)
    {
        echo 'save'."\n";
    }

    public function delete($key)
    {
        echo 'del'."\n";
    }
 }

interface BackendInterface {
    public function find($key, $lifetime);
    public function save($key, $value, $lifetime);
    public function delete($key);
}


class mongoDB implements BackendInterface
{
    public function find($key, $lifetime) { }
    public function save($key, $value, $lifetime) { }
    public function delete($key) { }
}

class file implements BackendInterface
{
    public function find($key, $lifetime) { }
    public function save($key, $value, $lifetime) { }
    public function delete($key) { }
}

$di = new Di();
//  redis
$di->set('redis', function() {
     return new redisDB([
         'host' => '127.0.0.1',
         'port' => 6379
     ]);
});
// mongodb
$di->set('mongo', function() {
     return new mongoDB([
         'host' => '127.0.0.1',
         'port' => 12707
     ]);
});
// file
$di->set('file', function() {
     return new file([
         'path' => 'path'
     ]);
});
// save at redis
$di->set('fastCache', function() use ($di) {
     $cache = new cache([
         'connect' => 'redis'
     ]);
     $cache->setDi($di);
     return $cache;
});
// save at mongodb
$di->set('cache', function() use ($di) {
     $cache = new cache([
         'connect' => 'mongo'
     ]);
     $cache->setDi($di);
     return $cache;
});
// save at file
$di->set('slowCache', function() use ($di) {
     $cache = new cache([
         'connect' => 'file'
     ]);
     $cache->setDi($di);
     return $cache;
});

// 然后在任何你想使用cache的地方
$cache = $di->get('cache');
$cache = $di->get('fastCache');
$cache->get('a');