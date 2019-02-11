<?php

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

 class RedisDB
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


    $di = new Di();
    $di->set('redis', function() {
         return new RedisDB([
             'host' => '127.0.0.1',
             'port' => 6379
         ]);
    });
    $di->set('cache', function() use ($di) {
        $cache = new Cache([
            'connect' => 'redis'
        ]);
        $cache->setDi($di);
        return $cache;
    });


    // 然后在任何你想使用cache的地方
    $cache = $di->get('cache');
    $cache->get('key'); // 获取缓存数据
    $cache->save('key', 'value', 'lifetime'); // 保存数据
    $cache->delete('key'); // 删除数据