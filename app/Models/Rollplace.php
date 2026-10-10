<?php

namespace App\Models;

use App\Core\Model;

/** Roll/seat placement state per session (table `rollplaces`). */
class Rollplace extends Model
{
    protected static string $table = 'rollplaces';
    protected static array $fillable = ['session', 'is_placed', 'created_at', 'updated_at'];
    protected static array $casts = ['id' => 'int', 'session' => 'int', 'is_placed' => 'int'];
}
