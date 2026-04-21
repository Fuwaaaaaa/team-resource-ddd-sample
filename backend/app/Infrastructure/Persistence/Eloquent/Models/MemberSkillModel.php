<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class MemberSkillModel extends Model
{
    protected $table = 'member_skills';
    protected $keyType = 'string';
    public $incrementing = false;

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
