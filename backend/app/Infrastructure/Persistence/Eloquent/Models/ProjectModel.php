<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectModel extends Model
{
    protected $table = 'projects';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
    ];

    public function requiredSkills(): HasMany
    {
        return $this->hasMany(RequiredSkillModel::class, 'project_id');
    }
}
