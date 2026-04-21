<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectModel extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'projects';

    protected $fillable = [
        'id',
        'name',
        'status',
    ];

    public function requiredSkills(): HasMany
    {
        return $this->hasMany(RequiredSkillModel::class, 'project_id');
    }

    protected static function newFactory(): Factory
    {
        return ProjectFactory::new();
    }
}
