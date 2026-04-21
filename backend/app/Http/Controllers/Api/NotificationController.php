<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $onlyUnread = $request->boolean('unread', false);

        $q = Notification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50);

        if ($onlyUnread) {
            $q->whereNull('read_at');
        }

        $items = $q->get();
        $unreadCount = Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => $items,
            'meta' => [
                'unreadCount' => $unreadCount,
            ],
        ]);
    }

    public function markRead(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $n = Notification::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $n->update(['read_at' => now()]);

        return response()->json(['data' => $n]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
