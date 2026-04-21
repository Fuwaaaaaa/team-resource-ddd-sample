<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Domain リポジトリ/サービスのインターフェースを Eloquent 実装に束ねるプロバイダ。
 *
 * Phase 1 時点ではスタブ。Phase 2 で Eloquent リポジトリ実装が揃い次第、
 * $bindings に以下を列挙する:
 *   MemberRepositoryInterface      => EloquentMemberRepository::class,
 *   ProjectRepositoryInterface     => EloquentProjectRepository::class,
 *   ResourceAllocationRepositoryInterface => EloquentAllocationRepository::class,
 *   SkillRepositoryInterface       => EloquentSkillRepository::class,
 *   AllocationServiceInterface     => AllocationService::class,
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
