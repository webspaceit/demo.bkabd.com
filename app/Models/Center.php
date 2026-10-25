<?php

namespace App\Models;

use App\Core\Model;

/**
 * Center. Account-backed centers use users.name; synchronized demo reference
 * rows can store display_name without creating a login account. institute_name
 * remains the physical venue.
 */
class Center extends Model
{
    protected static string $table = 'centers';
    protected static array $fillable = [
        'user_id', 'website', 'division_id', 'district_id', 'upazila_id', 'union_id',
        'authority_person', 'institute_name', 'code', 'active', 'note',
        'authority_photo', 'authority_signature', 'created_at', 'updated_at',
    ];
    protected static array $casts = [
        'id' => 'int', 'user_id' => 'int', 'division_id' => 'int', 'district_id' => 'int',
        'upazila_id' => 'int', 'union_id' => 'int', 'active' => 'int',
    ];
}
