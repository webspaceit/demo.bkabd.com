<?php

namespace App\Models;

use App\Core\Model;

/** Upazila (spelled `upozilas` in DB). */
class Upazila extends Model
{
    protected static string $table = 'upozilas';
    protected static array $fillable = ['name', 'district_id', 'user_id', 'created_at', 'updated_at'];
}
