<?php

namespace App\Models;

use App\Core\Model;

class Permission extends Model
{
    protected static string $table = 'permissions';
    protected static array $fillable = ['name', 'guard_name', 'created_at', 'updated_at'];
}
