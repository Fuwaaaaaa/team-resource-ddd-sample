<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberModel extends Model
{
    protected $table = 'members';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'standard_working_hours',
    ];

    protected $casts = [
        'standard_working_hours' => 'float',
    ];

    public function skills(): HasMany
    {
        return $this->hasMany(MemberSkillModel::class, 'member_id');
    }
}
