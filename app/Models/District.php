<?php

namespace App\Models;

use App\Core\Model;

class District extends Model
{
    protected static string $table = 'districts';
    protected static array $fillable = ['name', 'division_id', 'user_id', 'created_at', 'updated_at'];
}
