<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'aggregateType' => 'nullable|in:allocation,member,project,user,absence,allocation_change_request',
            'aggregateId' => 'nullable|uuid',
            'eventType' => 'nullable|string|max:80',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'userId' => 'nullable|integer|exists:users,id',
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
        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to);
        }
        if ($userId = $request->query('userId')) {
            $query->where('user_id', (int) $userId);
        }

        $paginator = $query->paginate((int) $request->query('perPage', 50));

        $items = collect($paginator->items());
        $labels = $this->resolveAggregateLabels($items);

        $data = $items->map(function (AuditLog $log) use ($labels): array {
            $arr = $log->toArray();
            $arr['aggregate_label'] = $labels[$log->aggregate_type][$log->aggregate_id] ?? null;

            return $arr;
        })->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * aggregate_id を人間可読な name に解決する lookup を構築する。
     * 1 ページ分のログをまとめて in-memory join するので N+1 を避けられる。
     *
     * 解決対象:
     *   - member: members.name
     *   - project: projects.name
     *   - user: users.name (payload.userId 経由で逆引き — aggregate_id は uuid v5 で users.id に直接対応しないため)
     * その他 (allocation / absence / allocation_change_request) は label = null。
     *
     * @param  Collection<int, AuditLog>  $items
     * @return array<string, array<string, string>> [aggregate_type => [aggregate_id => label]]
     */
    private function resolveAggregateLabels($items): array
    {
        $memberIds = $items->where('aggregate_type', 'member')->pluck('aggregate_id')->unique()->values();
        $projectIds = $items->where('aggregate_type', 'project')->pluck('aggregate_id')->unique()->values();

        $userPayloadIds = $items->where('aggregate_type', 'user')
            ->pluck('payload.userId')
            ->filter()
            ->unique()
            ->values();

        $labels = [
            'member' => $memberIds->isNotEmpty()
                ? DB::table('members')->whereIn('id', $memberIds)->pluck('name', 'id')->all()
                : [],
            'project' => $projectIds->isNotEmpty()
                ? DB::table('projects')->whereIn('id', $projectIds)->pluck('name', 'id')->all()
                : [],
        ];

        if ($userPayloadIds->isNotEmpty()) {
            $userNames = DB::table('users')->whereIn('id', $userPayloadIds)->pluck('name', 'id')->all();
            // user の lookup は aggregate_id (uuid v5) でなく payload.userId 経由なので、
            // ここで aggregate_id をキーにした逆引き表を作り直す。
            $userLabels = [];
            foreach ($items->where('aggregate_type', 'user') as $log) {
                $payloadUserId = $log->payload['userId'] ?? null;
                if ($payloadUserId !== null && isset($userNames[$payloadUserId])) {
                    $userLabels[$log->aggregate_id] = $userNames[$payloadUserId];
                }
            }
            $labels['user'] = $userLabels;
        }

        return $labels;
    }
}
