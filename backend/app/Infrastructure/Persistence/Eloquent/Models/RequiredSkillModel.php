<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class RequiredSkillModel extends Model
{
    protected $table = 'required_skills';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'project_id',
        'skill_id',
        'required_proficiency',
        'headcount',
    ];

    protected $casts = [
        'required_proficiency' => 'integer',
        'headcount' => 'integer',
    ];
}
