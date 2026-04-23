<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Dashboard;

use App\Application\Dashboard\Queries\GetCapacityForecastHandler;
use App\Application\Dashboard\Queries\GetCapacityForecastQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CapacityForecastRequest;
use Illuminate\Http\JsonResponse;

class CapacityForecastController extends Controller
{
    public function __invoke(
        CapacityForecastRequest $request,
        GetCapacityForecastHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new GetCapacityForecastQuery(
            referenceDate: $request->referenceDate(),
            monthsAhead: $request->monthsAhead(),
        ));

        return response()->json(['data' => $dto->toArray()], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
