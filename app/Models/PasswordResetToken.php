<?php

namespace App\Models;

use App\Core\Model;

class PasswordResetToken extends Model
{
    public static string $table = "password_reset_tokens";
}
