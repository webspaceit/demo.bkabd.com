<?php

namespace App\Models;

use App\Core\Model;

class Routine extends Model
{
    protected static string $table = 'routines';
    protected static array $fillable = ['subject', 'date', 'day', 'time', 'class', 'order', 'active', 'user_id', 'created_at', 'updated_at'];
}
