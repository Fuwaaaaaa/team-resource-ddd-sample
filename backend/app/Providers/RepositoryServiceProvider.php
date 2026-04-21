<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Allocation\ResourceAllocationRepositoryInterface;
use App\Domain\Member\MemberRepositoryInterface;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Service\AllocationServiceInterface;
use App\Domain\Skill\SkillRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentAllocationRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentMemberRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentProjectRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentSkillRepository;
use App\Infrastructure\Service\AllocationService;
use Illuminate\Support\ServiceProvider;

/**
 * Domain リポジトリ/サービスのインターフェースを Eloquent 実装に束ねるプロバイダ。
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        MemberRepositoryInterface::class => EloquentMemberRepository::class,
        ProjectRepositoryInterface::class => EloquentProjectRepository::class,
        ResourceAllocationRepositoryInterface::class => EloquentAllocationRepository::class,
        SkillRepositoryInterface::class => EloquentSkillRepository::class,
        AllocationServiceInterface::class => AllocationService::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
