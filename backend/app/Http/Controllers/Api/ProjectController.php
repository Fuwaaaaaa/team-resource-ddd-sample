<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Project\Commands\ChangeProjectStatusCommand;
use App\Application\Project\Commands\ChangeProjectStatusHandler;
use App\Application\Project\Commands\CreateProjectCommand;
use App\Application\Project\Commands\CreateProjectHandler;
use App\Application\Project\Commands\DeleteProjectHandler;
use App\Application\Project\Commands\UpdateProjectCommand;
use App\Application\Project\Commands\UpdateProjectHandler;
use App\Application\Project\Commands\UpsertRequiredSkillCommand;
use App\Application\Project\Commands\UpsertRequiredSkillHandler;
use App\Application\Project\DTOs\ProjectDto;
use App\Application\Project\Queries\GetProjectKpiHandler;
use App\Application\Project\Queries\GetProjectKpiQuery;
use App\Domain\Project\ProjectId;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\ChangeProjectStatusRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpsertRequiredSkillRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(ProjectRepositoryInterface $repository): JsonResponse
    {
        $projects = array_map(
            fn ($p) => ProjectDto::fromDomain($p),
            $repository->findAll(),
        );

        return response()->json(['data' => $projects]);
    }

    public function show(string $id, ProjectRepositoryInterface $repository): JsonResponse
    {
        $project = $repository->findById(new ProjectId($id));
        abort_if($project === null, 404, 'Project not found.');

        return response()->json(['data' => ProjectDto::fromDomain($project)]);
    }

    public function store(StoreProjectRequest $request, CreateProjectHandler $handler): JsonResponse
    {
        $dto = $handler->handle(new CreateProjectCommand(
            name: (string) $request->input('name'),
            plannedStartDate: $request->input('plannedStartDate'),
            plannedEndDate: $request->input('plannedEndDate'),
        ));

        return response()->json(['data' => $dto], 201);
    }

    public function update(string $id, StoreProjectRequest $request, UpdateProjectHandler $handler): JsonResponse
    {
        $dto = $handler->handle(new UpdateProjectCommand(
            projectId: $id,
            name: (string) $request->input('name'),
            plannedStartDate: $request->input('plannedStartDate'),
            plannedEndDate: $request->input('plannedEndDate'),
        ));

        return response()->json(['data' => $dto]);
    }

    public function destroy(string $id, DeleteProjectHandler $handler): JsonResponse
    {
        $handler->handle($id);

        return response()->json(null, 204);
    }

    public function kpi(
        string $id,
        Request $request,
        GetProjectKpiHandler $handler,
    ): JsonResponse {
        $request->validate([
            'referenceDate' => 'nullable|date_format:Y-m-d',
        ]);

        $dto = $handler->handle(new GetProjectKpiQuery(
            projectId: $id,
            referenceDate: (string) ($request->query('referenceDate') ?? date('Y-m-d')),
        ));

        return response()->json(['data' => $dto->toArray()], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function changeStatus(
        string $id,
        ChangeProjectStatusRequest $request,
        ChangeProjectStatusHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new ChangeProjectStatusCommand(
            projectId: $id,
            status: (string) $request->input('status'),
        ));

        return response()->json(['data' => $dto]);
    }

    public function upsertRequiredSkill(
        string $id,
        string $skillId,
        UpsertRequiredSkillRequest $request,
        UpsertRequiredSkillHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new UpsertRequiredSkillCommand(
            projectId: $id,
            skillId: $skillId,
            requiredProficiency: (int) $request->input('requiredProficiency'),
            headcount: (int) $request->input('headcount'),
        ));

        return response()->json(['data' => $dto]);
    }
}
