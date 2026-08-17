<?php
namespace App\Models;

use App\Core\DB;
use App\Core\Model;

class ApiClient extends Model
{
    protected static string $table = "api_client";

    public static function findByCredentials(string $client_id, string $client_secret)
    {
        return DB::table(self::$table)
            ->where('client_id', '=', $client_id)
            ->where('client_secret', '=', $client_secret)
            ->where('active', '=', 1)
            ->first();
    }

    public static function allActive()
    {
        return DB::table(self::$table)
            ->where('active', '=', 1)
            ->get();
    }

    public static function create($data)
    {
        return DB::table(self::$table)->insert($data);
    }

    public static function generateCredentials(): array
    {
        return [
            'client_id'     => bin2hex(random_bytes(16)),
            'client_secret' => bin2hex(random_bytes(32))
        ];
    }
}
