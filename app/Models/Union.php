<?php

namespace App\Models;

use App\Core\Model;

class Union extends Model
{
    protected static string $table = 'unions';
    protected static array $fillable = ['name', 'upazila_id', 'user_id', 'created_at', 'updated_at'];
}
