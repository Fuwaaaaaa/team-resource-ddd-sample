<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RequiredSkillModel extends Model
{
    use HasUuids;

    protected $table = 'required_skills';

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
