<?php

namespace App\Models;

use App\Core\Model;

/** Exam session row (table `sessions`): year, active flag, result gate. */
class ExamSession extends Model
{
    protected static string $table = 'sessions';
    protected static array $fillable = ['session', 'active', 'result', 'result_time', 'user_id', 'created_at', 'updated_at'];
    protected static array $casts = [
        'id' => 'int', 'session' => 'int', 'active' => 'int', 'result' => 'int', 'user_id' => 'int',
    ];
}
