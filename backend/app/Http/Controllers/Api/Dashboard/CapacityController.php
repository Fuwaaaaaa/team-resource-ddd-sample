<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Dashboard;

use App\Application\Dashboard\Queries\GetTeamCapacityHandler;
use App\Application\Dashboard\Queries\GetTeamCapacityQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardQueryRequest;
use Illuminate\Http\JsonResponse;

class CapacityController extends Controller
{
    public function __invoke(
        DashboardQueryRequest $request,
        GetTeamCapacityHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new GetTeamCapacityQuery($request->referenceDate()));

        return response()->json($dto);
    }
}
