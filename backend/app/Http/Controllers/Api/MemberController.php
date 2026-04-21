<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Member\Commands\CreateMemberCommand;
use App\Application\Member\Commands\CreateMemberHandler;
use App\Application\Member\Commands\DeleteMemberHandler;
use App\Application\Member\Commands\UpdateMemberCommand;
use App\Application\Member\Commands\UpdateMemberHandler;
use App\Application\Member\Commands\UpsertMemberSkillCommand;
use App\Application\Member\Commands\UpsertMemberSkillHandler;
use App\Application\Member\DTOs\MemberDto;
use App\Domain\Member\MemberId;
use App\Domain\Member\MemberRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\StoreMemberRequest;
use App\Http\Requests\Member\UpdateMemberRequest;
use App\Http\Requests\Member\UpsertMemberSkillRequest;
use Illuminate\Http\JsonResponse;

class MemberController extends Controller
{
    public function index(MemberRepositoryInterface $repository): JsonResponse
    {
        $members = array_map(
            fn ($m) => MemberDto::fromDomain($m),
            $repository->findAll(),
        );

        return response()->json(['data' => $members]);
    }

    public function show(string $id, MemberRepositoryInterface $repository): JsonResponse
    {
        $member = $repository->findById(new MemberId($id));
        abort_if($member === null, 404, 'Member not found.');

        return response()->json(['data' => MemberDto::fromDomain($member)]);
    }

    public function store(StoreMemberRequest $request, CreateMemberHandler $handler): JsonResponse
    {
        $dto = $handler->handle(new CreateMemberCommand(
            name: (string) $request->input('name'),
            standardWorkingHours: (float) ($request->input('standardWorkingHours') ?? 8.0),
        ));

        return response()->json(['data' => $dto], 201);
    }

    public function update(string $id, UpdateMemberRequest $request, UpdateMemberHandler $handler): JsonResponse
    {
        $dto = $handler->handle(new UpdateMemberCommand(
            memberId: $id,
            name: $request->input('name'),
            standardWorkingHours: $request->has('standardWorkingHours')
                ? (float) $request->input('standardWorkingHours')
                : null,
        ));

        return response()->json(['data' => $dto]);
    }

    public function destroy(string $id, DeleteMemberHandler $handler): JsonResponse
    {
        $handler->handle($id);

        return response()->json(null, 204);
    }

    public function upsertSkill(
        string $id,
        string $skillId,
        UpsertMemberSkillRequest $request,
        UpsertMemberSkillHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new UpsertMemberSkillCommand(
            memberId: $id,
            skillId: $skillId,
            proficiency: (int) $request->input('proficiency'),
        ));

        return response()->json(['data' => $dto]);
    }
}
