<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Dashboard\DTOs\SkillDto;
use App\Domain\Skill\SkillRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SkillController extends Controller
{
    public function index(SkillRepositoryInterface $repository): JsonResponse
    {
        $skills = array_map(
            fn ($s) => new SkillDto(
                id: $s->id()->toString(),
                name: $s->name()->toString(),
                category: $s->category()->toString(),
            ),
            $repository->findAll(),
        );

        return response()->json(['data' => $skills]);
    }
}
