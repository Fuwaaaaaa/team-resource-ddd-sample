<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Dashboard;

use App\Application\Dashboard\Queries\GetKpiSummaryHandler;
use App\Application\Dashboard\Queries\GetKpiSummaryQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardQueryRequest;
use Illuminate\Http\JsonResponse;

class KpiSummaryController extends Controller
{
    public function __invoke(
        DashboardQueryRequest $request,
        GetKpiSummaryHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new GetKpiSummaryQuery($request->referenceDate()));

        return response()->json(['data' => $dto->toArray()], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
