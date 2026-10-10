<?php

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected static string $table = 'users';
    protected static array $fillable = [
        'name', 'email', 'phone', 'password', 'avatar', 'type',
        'remember_token', 'created_at', 'updated_at',
    ];
    protected static array $casts = ['id' => 'int', 'type' => 'int'];
}
