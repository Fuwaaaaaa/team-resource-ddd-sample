<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\AllocationChangeRequest\Commands\ApproveAllocationChangeRequestHandler;
use App\Application\AllocationChangeRequest\Commands\RejectAllocationChangeRequestHandler;
use App\Application\AllocationChangeRequest\Commands\SubmitAllocationChangeRequestCommand;
use App\Application\AllocationChangeRequest\Commands\SubmitAllocationChangeRequestHandler;
use App\Application\AllocationChangeRequest\Queries\ListAllocationChangeRequestsHandler;
use App\Application\AllocationChangeRequest\Queries\ListAllocationChangeRequestsQuery;
use App\Domain\Authorization\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\AllocationChangeRequest\DecideChangeRequest;
use App\Http\Requests\AllocationChangeRequest\SubmitChangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AllocationChangeRequestController extends Controller
{
    public function index(
        Request $request,
        ListAllocationChangeRequestsHandler $handler,
    ): JsonResponse {
        $user = $request->user();

        // manager は自分が申請したものだけ、admin は全件。viewer も読み取り可能 (自分が申請したもののみ)
        $requestedBy = $user->role === UserRole::Admin ? null : (int) $user->id;

        $dtos = $handler->handle(new ListAllocationChangeRequestsQuery(
            status: $request->query('status'),
            requestedBy: $requestedBy,
        ));

        return response()->json([
            'data' => array_map(fn ($d) => $d->toArray(), $dtos),
        ]);
    }

    public function store(
        SubmitChangeRequest $request,
        SubmitAllocationChangeRequestHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new SubmitAllocationChangeRequestCommand(
            type: (string) $request->input('type'),
            payload: (array) $request->input('payload'),
            requestedBy: (int) $request->user()->id,
            reason: $request->input('reason'),
        ));

        return response()->json(['data' => $dto->toArray()], 201);
    }

    public function approve(
        string $id,
        DecideChangeRequest $request,
        ApproveAllocationChangeRequestHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle($id, (int) $request->user()->id, $request->input('note'));

        return response()->json(['data' => $dto->toArray()]);
    }

    public function reject(
        string $id,
        DecideChangeRequest $request,
        RejectAllocationChangeRequestHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle($id, (int) $request->user()->id, $request->input('note'));

        return response()->json(['data' => $dto->toArray()]);
    }
}
