<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkillModel extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'skills';

    protected $fillable = [
        'id',
        'name',
        'category',
    ];

    protected static function newFactory(): Factory
    {
        return SkillFactory::new();
    }
}
