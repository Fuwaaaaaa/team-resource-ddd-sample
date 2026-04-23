<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DomainEventModel extends Model
{
    use HasUuids;

    protected $table = 'domain_events';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'stream_type',
        'stream_id',
        'stream_version',
        'event_type',
        'event_data',
        'metadata',
        'occurred_at',
        'created_at',
    ];

    protected $casts = [
        'stream_version' => 'integer',
        'event_data' => 'array',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
