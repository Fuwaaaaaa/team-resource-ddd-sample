<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectModel extends Model
{
    use HasUuids;

    protected $table = 'projects';

    protected $fillable = [
        'id',
        'name',
    ];

    public function requiredSkills(): HasMany
    {
        return $this->hasMany(RequiredSkillModel::class, 'project_id');
    }
}
