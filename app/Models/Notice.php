<?php

namespace App\Models;

use App\Core\Model;

class Notice extends Model
{
    protected static string $table = 'notices';
    protected static array $fillable = ['title', 'description', 'center_id', 'user_id', 'created_at', 'updated_at'];
}
