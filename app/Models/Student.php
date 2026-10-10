<?php

namespace App\Models;

use App\Core\Model;

class Student extends Model
{
    protected static string $table = 'students';
    protected static array $fillable = [
        'name', 'father_name', 'mother_name', 'roll', 'registration', 'phone', 'email',
        'dob', 'nationality', 'religion', 'gender', 'permanent_address', 'present_address',
        'class', 'session', 'education_type', 'principal_name', 'center_id', 'institute_id',
        'division_id', 'district_id', 'upazila_id', 'union_id', 'photo', 'paid', 'active',
        'note', 'user_id', 'bangla', 'english', 'math', 'total', 'percentage', 'grade',
        'ml', 'place_id', 'marker_id', 'created_at', 'updated_at',
    ];
    protected static array $casts = [
        'id' => 'int', 'roll' => 'int', 'class' => 'int', 'session' => 'int',
        'center_id' => 'int', 'institute_id' => 'int', 'division_id' => 'int',
        'district_id' => 'int', 'upazila_id' => 'int', 'union_id' => 'int',
        'bangla' => 'int', 'english' => 'int', 'math' => 'int', 'total' => 'int',
        'percentage' => 'float', 'grade' => 'int', 'ml' => 'int', 'place_id' => 'int',
        'paid' => 'int', 'active' => 'int', 'marker_id' => 'int', 'user_id' => 'int',
    ];
}
