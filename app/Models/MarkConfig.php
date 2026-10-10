<?php

namespace App\Models;

use App\Core\Model;

/** Full-marks configuration per session (table `marks`). */
class MarkConfig extends Model
{
    protected static string $table = 'marks';
    protected static array $fillable = ['bangla', 'english', 'math', 'total', 'user_id', 'session', 'created_at', 'updated_at'];
    protected static array $casts = [
        'id' => 'int', 'bangla' => 'int', 'english' => 'int', 'math' => 'int',
        'total' => 'int', 'session' => 'int', 'user_id' => 'int',
    ];
}
