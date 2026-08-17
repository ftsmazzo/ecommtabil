<?php

namespace App\Models;

use App\Core\DB;
use App\Core\Model;

class WebpushSubscription extends Model
{
    public static string $table = "webpush_subscriptions";
    public static ?string $alias = "wps";

    public static function saveOrUpdate($token, $endpoint, $p256dh, $auth)
    {
        $fields = [
            "user_token" => $token,     // <-- importante: coluna certa
            "p256dh"     => $p256dh,
            "auth"       => $auth,
        ];

        $exists = DB::table(self::$table)->where("endpoint", "=", $endpoint)->first();

        if ($exists) {
            DB::table(self::$table)->where("endpoint", "=", $endpoint)->update($fields);
        } else {
            $fields["endpoint"] = $endpoint;
            DB::table(self::$table)->insert($fields);
        }
    }

    public static function deleteByEndpoint($endpoint)
    {
        $endpoint = trim($endpoint);

        $sql = "DELETE FROM webpush_subscriptions WHERE endpoint = ?";

        // ajuste para o seu DB (não sei se é DB::execute, DB::run, DB::query...)
        // o importante: retornar linhas afetadas
        return DB::execute($sql, [$endpoint]);
    }
}