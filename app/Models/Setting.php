<?php

namespace App\Models;

use App\Core\Model;

class Setting extends Model
{
    protected static string $table = 'settings';
    protected static array $fillable = ['title', 'description', 'active', 'user_id', 'created_at', 'updated_at'];
}
