<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Dashboard;

use App\Application\Dashboard\Queries\GetKpiTrendHandler;
use App\Application\Dashboard\Queries\GetKpiTrendQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\KpiTrendRequest;
use Illuminate\Http\JsonResponse;

class KpiTrendController extends Controller
{
    public function __invoke(
        KpiTrendRequest $request,
        GetKpiTrendHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new GetKpiTrendQuery(
            referenceDate: $request->referenceDate(),
            days: $request->days(),
        ));

        return response()->json(['data' => $dto->toArray()], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
