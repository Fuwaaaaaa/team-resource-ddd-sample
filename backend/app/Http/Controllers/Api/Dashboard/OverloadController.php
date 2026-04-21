<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Dashboard;

use App\Application\Dashboard\Queries\GetOverloadAnalysisHandler;
use App\Application\Dashboard\Queries\GetOverloadAnalysisQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardQueryRequest;
use Illuminate\Http\JsonResponse;

class OverloadController extends Controller
{
    public function __invoke(
        DashboardQueryRequest $request,
        GetOverloadAnalysisHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new GetOverloadAnalysisQuery($request->referenceDate()));

        return response()->json($dto);
    }
}
