<?php

namespace App\Models;

use App\Core\Model;

class Division extends Model
{
    protected static string $table = 'divisions';
    protected static array $fillable = ['name', 'user_id', 'created_at', 'updated_at'];
}
