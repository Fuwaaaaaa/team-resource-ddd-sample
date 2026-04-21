<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Allocation\Commands\CreateAllocationCommand;
use App\Application\Allocation\Commands\CreateAllocationHandler;
use App\Application\Allocation\Commands\RevokeAllocationHandler;
use App\Application\Allocation\DTOs\AllocationDto;
use App\Domain\Allocation\ResourceAllocationRepositoryInterface;
use App\Domain\Member\MemberId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Allocation\StoreAllocationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AllocationController extends Controller
{
    public function index(Request $request, ResourceAllocationRepositoryInterface $repository): JsonResponse
    {
        $memberId = $request->query('memberId');
        if (! is_string($memberId) || $memberId === '') {
            abort(400, 'memberId query parameter is required.');
        }

        $allocations = $repository->findByMemberId(new MemberId($memberId));
        return response()->json([
            'data' => array_map(fn ($a) => AllocationDto::fromDomain($a), $allocations),
        ]);
    }

    public function store(StoreAllocationRequest $request, CreateAllocationHandler $handler): JsonResponse
    {
        $dto = $handler->handle(new CreateAllocationCommand(
            memberId: (string) $request->input('memberId'),
            projectId: (string) $request->input('projectId'),
            skillId: (string) $request->input('skillId'),
            allocationPercentage: (int) $request->input('allocationPercentage'),
            periodStart: (string) $request->input('periodStart'),
            periodEnd: (string) $request->input('periodEnd'),
        ));
        return response()->json(['data' => $dto], 201);
    }

    public function revoke(string $id, RevokeAllocationHandler $handler): JsonResponse
    {
        $dto = $handler->handle($id);
        return response()->json(['data' => $dto]);
    }
}
