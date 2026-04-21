<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Allocation\AllocationPercentage;
use App\Domain\Allocation\ResourceAllocation;
use App\Domain\Availability\Absence;
use App\Domain\Member\Member;
use App\Domain\Member\MemberId;
use App\Domain\Project\Project;
use App\Domain\Project\ProjectId;
use App\Domain\Skill\SkillId;
use DateTimeImmutable;

/**
 * 集約横断のビジネスロジックを担うドメインサービス。
 * ステートレスであり、必要なデータはすべて引数で受け取る。
 * リポジトリを直接呼ばず、Application層のHandlerがデータを渡す。
 */
interface AllocationServiceInterface
{
    /**
     * プロジェクトのリソース過不足を計算する。
     *
     * 各RequiredSkillについて:
     *  1. 基準日時点でアクティブなアロケーションを抽出
     *  2. 各メンバーのSkillProficiencyがRequiredProficiencyを満たすか判定
     *  3. 「適格人数」vs「必要人数」のギャップを算出
     *
     * @param  Project  $project  RequiredSkillを持つプロジェクト
     * @param  ResourceAllocation[]  $allocations  このプロジェクトのアロケーション
     * @param  Member[]  $members  アロケーション対象メンバー
     * @param  DateTimeImmutable  $referenceDate  基準日
     * @return ResourceSurplusDeficit スキル別の過不足分析結果
     */
    public function calculateSurplusDeficit(
        Project $project,
        array $allocations,
        array $members,
        DateTimeImmutable $referenceDate
    ): ResourceSurplusDeficit;

    /**
     * チーム全体のキャパシティスナップショットを構築する。
     *
     * 各メンバーについて、スキル別の熟練度と未割り当て工数を算出。
     * ダッシュボードの「スキルマップヒートマップ」描画に使用。
     *
     * @param  Member[]  $members  全チームメンバー
     * @param  ResourceAllocation[]  $allocations  全アクティブアロケーション
     * @param  DateTimeImmutable  $referenceDate  基準日
     * @return TeamCapacitySnapshot メンバー×スキルの可用性マトリクス
     */
    public function buildTeamCapacitySnapshot(
        array $members,
        array $allocations,
        DateTimeImmutable $referenceDate
    ): TeamCapacitySnapshot;

    /**
     * メンバーへのアロケーションが100%上限を超えないか検証する。
     *
     * @param  ResourceAllocation[]  $existingAllocations  該当メンバーの既存アロケーション
     * @return bool 割り当て可能な場合true
     */
    public function canAllocate(
        MemberId $memberId,
        AllocationPercentage $requestedPercentage,
        array $existingAllocations,
        DateTimeImmutable $referenceDate
    ): bool;

    /**
     * 複数プロジェクト横断のスキルギャップ分析を実行する。
     *
     * 全プロジェクトのRequiredSkillを集約し、チームの実スキルセットと
     * 現在のアロケーション状況と比較。需要が供給を上回るスキルを
     * 優先度順にリスト化する。
     *
     * @param  Project[]  $projects
     * @param  Member[]  $members
     * @param  ResourceAllocation[]  $allocations
     * @return SkillGapAnalysis 優先度付きスキル不足リスト
     */
    public function analyzeSkillGaps(
        array $projects,
        array $members,
        array $allocations,
        DateTimeImmutable $referenceDate
    ): SkillGapAnalysis;

    /**
     * メンバーの過負荷状態を分析する。
     *
     * 各メンバーの標準労働時間とアクティブなアロケーション合計を比較し、
     * 100%を超える割り当てがあるメンバーを検出する。
     * $absences が渡されたとき、基準日に該当メンバーの有効な不在があれば
     * 実稼働可能時間は 0 とみなし、全ての割当が過負荷としてカウントされる。
     *
     * @param  Member[]  $members
     * @param  ResourceAllocation[]  $allocations
     * @param  DateTimeImmutable  $referenceDate  基準日
     * @param  Absence[]  $absences  当該日に考慮する不在リスト
     * @return OverloadAnalysis メンバー別の過負荷分析結果
     */
    public function detectOverload(
        array $members,
        array $allocations,
        DateTimeImmutable $referenceDate,
        array $absences = []
    ): OverloadAnalysis;

    /**
     * プロジェクトに割り当てられたメンバーのスキル不足警告を生成する。
     *
     * 各アロケーションについて、メンバーの実スキル熟練度が
     * プロジェクトの要求水準を満たさない場合に警告を生成する。
     *
     * @param  ResourceAllocation[]  $allocations  このプロジェクトのアロケーション
     * @param  Member[]  $members
     * @param  DateTimeImmutable  $referenceDate  基準日
     * @return SkillGapWarning[] スキル不足警告リスト
     */
    public function detectSkillGapWarnings(
        Project $project,
        array $allocations,
        array $members,
        DateTimeImmutable $referenceDate
    ): array;

    /**
     * 必要スキルに対するアサインメント候補を提案する。
     *
     * スコアリング指標:
     *   - 余剰キャパ (period 開始日時点で 100 - 使用率)
     *   - 熟練度余裕 (member.proficiency - minimumProficiency)
     *   - 同プロジェクト経験歴 (該当プロジェクトの過去 allocation 数)
     *
     * @param  Member[]  $members  全メンバー
     * @param  ResourceAllocation[]  $allocations  全アロケーション (revoked 含む)
     * @return AllocationCandidate[] スコア降順、最大 $limit 件
     */
    public function suggestCandidates(
        SkillId $skillId,
        int $minimumProficiency,
        ProjectId $projectId,
        DateTimeImmutable $periodStart,
        array $members,
        array $allocations,
        int $limit = 5
    ): array;
}
