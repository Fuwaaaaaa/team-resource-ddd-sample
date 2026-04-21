<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Service;

use App\Domain\Availability\Absence;
use App\Domain\Availability\AbsenceId;
use App\Domain\Availability\AbsencePeriod;
use App\Domain\Availability\AbsenceType;
use App\Domain\Member\MemberId;
use App\Infrastructure\Service\AllocationService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\AllocationTestFactory;

final class AllocationServiceAbsenceTest extends TestCase
{
    use AllocationTestFactory;

    private AllocationService $service;

    protected function setUp(): void
    {
        $this->service = new AllocationService();
    }

    public function test_absence_on_reference_date_treats_all_allocation_as_overload(): void
    {
        $member = $this->makeMember('m1', 8.0);
        $allocations = [
            $this->makeAllocation('m1', 'p1', 'php', 50),
        ];
        $absences = [
            new Absence(
                new AbsenceId('abs-1'),
                new MemberId('m1'),
                new AbsencePeriod(
                    new DateTimeImmutable('2025-06-14'),
                    new DateTimeImmutable('2025-06-16'),
                ),
                AbsenceType::Vacation,
            ),
        ];

        $result = $this->service->detectOverload($member ? [$member] : [], $allocations, $this->referenceDate(), $absences);

        $entry = $result->entries()[0];
        // 50% of 8h = 4h; all of it is over because available hours = 0
        $this->assertSame(50, $entry->totalAllocatedPercentage());
        $this->assertEqualsWithDelta(4.0, $entry->overloadHours(), 0.001);
        $this->assertSame(0.0, $entry->standardHoursPerDay(), '不在日は実効稼働時間 0');
    }

    public function test_canceled_absence_does_not_affect_overload(): void
    {
        $member = $this->makeMember('m1', 8.0);
        $allocations = [
            $this->makeAllocation('m1', 'p1', 'php', 50),
        ];
        $absence = new Absence(
            new AbsenceId('abs-1'),
            new MemberId('m1'),
            new AbsencePeriod(
                new DateTimeImmutable('2025-06-14'),
                new DateTimeImmutable('2025-06-16'),
            ),
            AbsenceType::Vacation,
        );
        $absence->cancel();

        $result = $this->service->detectOverload([$member], $allocations, $this->referenceDate(), [$absence]);

        $entry = $result->entries()[0];
        // Canceled absence is ignored; 50% ≤ 100% so no overload
        $this->assertSame(0.0, $entry->overloadHours());
        $this->assertEqualsWithDelta(8.0, $entry->standardHoursPerDay(), 0.001);
    }

    public function test_absence_outside_reference_date_ignored(): void
    {
        $member = $this->makeMember('m1', 8.0);
        $allocations = [
            $this->makeAllocation('m1', 'p1', 'php', 50),
        ];
        $absences = [
            new Absence(
                new AbsenceId('abs-1'),
                new MemberId('m1'),
                new AbsencePeriod(
                    new DateTimeImmutable('2025-07-01'),
                    new DateTimeImmutable('2025-07-03'),
                ),
                AbsenceType::Vacation,
            ),
        ];

        $result = $this->service->detectOverload([$member], $allocations, $this->referenceDate('2025-06-15'), $absences);

        $entry = $result->entries()[0];
        $this->assertSame(0.0, $entry->overloadHours());
    }

    public function test_other_member_absence_ignored(): void
    {
        $m1 = $this->makeMember('m1', 8.0);
        $m2 = $this->makeMember('m2', 8.0);
        $allocations = [
            $this->makeAllocation('m1', 'p1', 'php', 50),
            $this->makeAllocation('m2', 'p2', 'java', 50),
        ];
        $absences = [
            new Absence(
                new AbsenceId('abs-1'),
                new MemberId('m2'), // m2 is absent
                new AbsencePeriod(
                    new DateTimeImmutable('2025-06-14'),
                    new DateTimeImmutable('2025-06-16'),
                ),
                AbsenceType::Vacation,
            ),
        ];

        $result = $this->service->detectOverload([$m1, $m2], $allocations, $this->referenceDate(), $absences);

        $entries = $result->entries();
        // m1 normal; m2 on absence => any allocation is overload
        $this->assertSame(0.0, $entries[0]->overloadHours(), 'm1 は不在でないので過負荷なし');
        $this->assertEqualsWithDelta(4.0, $entries[1]->overloadHours(), 0.001, 'm2 は不在なので 50% 全部が過負荷');
    }

    public function test_detectOverload_without_absences_matches_original_behavior(): void
    {
        $member = $this->makeMember('m1', 8.0);
        $allocations = [
            $this->makeAllocation('m1', 'p1', 'php', 100),
        ];

        // 明示的な空配列 vs デフォルト引数が一致することを確認
        $a = $this->service->detectOverload([$member], $allocations, $this->referenceDate(), []);
        $b = $this->service->detectOverload([$member], $allocations, $this->referenceDate());

        $this->assertSame(
            $a->entries()[0]->overloadHours(),
            $b->entries()[0]->overloadHours(),
        );
    }
}
