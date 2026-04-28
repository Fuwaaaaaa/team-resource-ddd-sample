<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'aggregateType' => 'nullable|in:allocation,member,project,user',
            'aggregateId' => 'nullable|uuid',
            'eventType' => 'nullable|string|max:80',
            'perPage' => 'nullable|integer|min:1|max:200',
        ]);

        $query = AuditLog::query()->with('user:id,name,email')->orderByDesc('created_at');

        if ($aggregateType = $request->query('aggregateType')) {
            $query->where('aggregate_type', $aggregateType);
        }
        if ($aggregateId = $request->query('aggregateId')) {
            $query->where('aggregate_id', $aggregateId);
        }
        if ($eventType = $request->query('eventType')) {
            $query->where('event_type', $eventType);
        }

        $paginator = $query->paginate((int) $request->query('perPage', 50));

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }
}
