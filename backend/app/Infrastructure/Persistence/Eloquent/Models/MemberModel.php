<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberModel extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'members';

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

    protected static function newFactory(): Factory
    {
        return MemberFactory::new();
    }
}
