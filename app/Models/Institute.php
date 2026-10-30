<?php

namespace App\Models;

use App\Core\Model;

class Institute extends Model
{
    protected static string $table = 'institutes';
    protected static array $fillable = [
        'center_id', 'name', 'phone', 'email', 'website', 'division_id', 'district_id',
        'upazila_id', 'union_id', 'director_name', 'address', 'zip', 'country',
        'established_date', 'type', 'recognized', 'membership_type', 'member_type',
        'certificate_no', 'total_students', 'total_teachers', 'institution_run',
        'monthly_income', 'monthly_expense', 'logo', 'principal_photo', 'paid', 'active',
        'note', 'user_id', 'created_at', 'updated_at',
    ];
    protected static array $casts = [
        'id' => 'int', 'center_id' => 'int', 'division_id' => 'int', 'district_id' => 'int',
        'upazila_id' => 'int', 'union_id' => 'int', 'type' => 'int', 'recognized' => 'int',
        'membership_type' => 'int', 'member_type' => 'int', 'paid' => 'int', 'active' => 'int',
        'user_id' => 'int',
    ];

    /** Number of institutes waiting for approval. Kept uncached for live badges. */
    public static function pendingCount(?int $centerId = null): int
    {
        $query = static::query()->whereRaw('(`active` = 0 OR `active` IS NULL)');
        if ($centerId !== null && $centerId > 0) {
            $query->where('center_id', $centerId);
        }
        return $query->count();
    }
}
