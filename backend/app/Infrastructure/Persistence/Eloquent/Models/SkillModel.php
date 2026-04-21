<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SkillModel extends Model
{
    use HasUuids;

    protected $table = 'skills';

    protected $fillable = [
        'id',
        'name',
        'category',
    ];
}
