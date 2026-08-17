<?php
namespace App\Lib;

class Redis
{

    /** @var Config */
    private $config;

    /** @var Redis */
    private $redis;

    public function __construct()
    {
        /*
        $this->config = Config::get("redis");

        try {

            $this->redis = new Redis();

            $this->redis->connect(
                                    $this->config["host"],
                                    $this->config["port"],
                                    $this->config["timeout"],
                                    $this->config["socket"],
                                    $this->config["interval"]
                                );

        } catch (Exception $redisEx) {

            echo $redisEx->getMessage();
        }

        return $this;
        */
    }

    // public function __get($name)
    // {

    //     if ($this->has($name)) {

    //         return $this->get($name);
    //     }
    //     return null;
    // }

    // public function __isset($name)
    // {
    //     $this->has($name);
    // }

    public function has(string $key): bool
    {
        // return $this->redis->exists($key);
        return false;
    }

    public function set($key, $value, $time = null)
    {

        // if (!$key)
        //     throw new Exception("The key is required!");

        // if (!$value)
        //     throw new Exception("The value is required!");

        // if (is_array($value)||is_object($value)) {

        //     $value = json_encode($value);
        // }

        // if ($time) {

        //     if (!is_int($time)) {

        //         throw new Exception("The time is invalid!");
        //         exit;
        //     }

        //     $this->redis->setex($key, $time, $value);

        // } else {

        //     $this->redis->set($key, $value);

        // }
        // return $this;
    }

    public function get($key)
    {
        // if (!$key)
        //     throw new Exception("The key is required!");

        // if (is_array($key)) {

        //     $result = $this->redis->mget($key);

        // } else {

        //     $result = $this->redis->get($key);

        //     if (is_json($result)) {
        //         $result = json_decode($result);
        //     }

        // }

        // return $result;

    }

    public function all($keys=true)
    {
        // $values = $this->redis->keys('*');

        // if (!$keys) {

        //     return $values;

        // } else {

        //     return $this->find("*");
        // }
    }

    public function find($key, $side="both")
    {

        // if (!$key)
        //     throw new Exception("The key is required!");

        // if ($side=="both") {

        //     $keys = $this->redis->keys('*'.$key.'*');

        // } elseif ($side=="start") {

        //     $keys = $this->redis->keys('*'.$key);

        // } else {

        //     $keys = $this->redis->keys($key.'*');
        // }

        // $return = [];
        // foreach ($keys as $key) {

        //     $return[$key] = $this->get($key);
        // }

        // return count($return)>0 ? $return : null;
    }

    public function del(string $key): Cache
    {
        // if (!$key)
        //     throw new Exception("The key is required!");

        // $this->redis->del($key);
        // return $this;
    }

    public function unset(string $key)
    {
        // $this->del($key);
    }

    public function clear()
    {
        // $this->redis->flushAll();
        // return $this;
    }

    public function destroy()
    {
        // $this->clear();
    }

}
