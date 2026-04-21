<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Dashboard;

use App\Application\Dashboard\Queries\GetSkillGapWarningsHandler;
use App\Application\Dashboard\Queries\GetSkillGapWarningsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SkillGapQueryRequest;
use Illuminate\Http\JsonResponse;

class SkillGapController extends Controller
{
    public function __invoke(
        SkillGapQueryRequest $request,
        GetSkillGapWarningsHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new GetSkillGapWarningsQuery(
            referenceDate: $request->referenceDate(),
            projectId: $request->projectId(),
        ));

        return response()->json($dto);
    }
}
