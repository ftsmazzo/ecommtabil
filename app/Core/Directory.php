<?php
namespace App\Core;

class Directory
{

    public static function has($path)
    {
        is_dir($path);
    }

    public static function create($path, $mode="755", $resursive=FALSE)
    {
        if (!self::has($path))
            mkdir($path, $mode, $resursive);
    }

    public static function remove($path)
    {
        if (self::has($path))
            rmdir($path);
    }

    public static function rename($old, $new)
    {
        if (self::has($old)&&self::has($new))
        rename($old, $new);
    }

}
