<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MemberSkillModel extends Model
{
    use HasUuids;

    protected $table = 'member_skills';

    protected $fillable = [
        'id',
        'member_id',
        'skill_id',
        'proficiency',
    ];

    protected $casts = [
        'proficiency' => 'integer',
    ];
}
