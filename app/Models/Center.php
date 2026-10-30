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

    /** Account-backed centers awaiting approval; reference-only sync rows are excluded. */
    public static function pendingCount(): int
    {
        return (int) \App\Core\Cache::remember('pending_centers:all', 1, static function (): int {
            return static::query()
                ->join('users', 'users.id', '=', 'centers.user_id')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->whereRaw('`centers`.`active` = 0 AND `model_has_roles`.`role_id` = ?', [4])
                ->count();
        });
    }
}
