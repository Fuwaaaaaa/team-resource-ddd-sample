<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Availability\Commands\CancelAbsenceHandler;
use App\Application\Availability\Commands\RegisterAbsenceCommand;
use App\Application\Availability\Commands\RegisterAbsenceHandler;
use App\Application\Availability\DTOs\AbsenceDto;
use App\Application\Availability\Queries\ListAbsencesByMemberHandler;
use App\Domain\Availability\AbsenceRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Availability\StoreAbsenceRequest;
use Illuminate\Http\JsonResponse;

class AbsenceController extends Controller
{
    public function index(AbsenceRepositoryInterface $repository): JsonResponse
    {
        $absences = array_map(
            fn ($a) => AbsenceDto::fromDomain($a)->toArray(),
            $repository->findAll(),
        );

        return response()->json(['data' => $absences]);
    }

    public function byMember(string $memberId, ListAbsencesByMemberHandler $handler): JsonResponse
    {
        $absences = $handler->handle($memberId);

        return response()->json([
            'data' => array_map(fn (AbsenceDto $d) => $d->toArray(), $absences),
        ]);
    }

    public function store(StoreAbsenceRequest $request, RegisterAbsenceHandler $handler): JsonResponse
    {
        $dto = $handler->handle(new RegisterAbsenceCommand(
            memberId: (string) $request->input('memberId'),
            startDate: (string) $request->input('startDate'),
            endDate: (string) $request->input('endDate'),
            type: (string) $request->input('type'),
            note: (string) ($request->input('note') ?? ''),
        ));

        return response()->json(['data' => $dto->toArray()], 201);
    }

    public function cancel(string $id, CancelAbsenceHandler $handler): JsonResponse
    {
        $dto = $handler->handle($id);

        return response()->json(['data' => $dto->toArray()]);
    }
}
