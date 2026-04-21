<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Note\StoreNoteRequest;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entityType' => ['required', Rule::in(['member', 'project', 'allocation'])],
            'entityId' => ['required', 'uuid'],
        ]);

        $notes = Note::query()
            ->with('author:id,name')
            ->where('entity_type', $validated['entityType'])
            ->where('entity_id', $validated['entityId'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => $notes]);
    }

    public function store(StoreNoteRequest $request): JsonResponse
    {
        $user = $request->user();

        $note = Note::create([
            'id' => (string) Str::uuid7(),
            'entity_type' => (string) $request->input('entityType'),
            'entity_id' => (string) $request->input('entityId'),
            'author_id' => $user?->id,
            'body' => (string) $request->input('body'),
        ]);

        $note->load('author:id,name');

        return response()->json(['data' => $note], 201);
    }

    public function destroy(string $id, Request $request): JsonResponse
    {
        $note = Note::findOrFail($id);

        // 自分のメモまたは admin のみ削除可
        $user = $request->user();
        $isAdmin = $user !== null && method_exists($user, 'canViewAuditLog') && $user->canViewAuditLog();
        if (! $isAdmin && $note->author_id !== $user?->id) {
            abort(403, 'Cannot delete others\' notes.');
        }

        $note->delete();

        return response()->json(null, 204);
    }
}
