<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Availability;

use App\Domain\Availability\Absence;
use App\Domain\Availability\AbsenceId;
use App\Domain\Availability\AbsencePeriod;
use App\Domain\Availability\AbsenceType;
use App\Domain\Availability\Events\AbsenceCanceled;
use App\Domain\Availability\Events\AbsenceRegistered;
use App\Domain\Member\MemberId;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AbsenceTest extends TestCase
{
    public function test_creates_absence_and_emits_registered_event(): void
    {
        $absence = $this->makeAbsence('2026-05-01', '2026-05-03', AbsenceType::Vacation);

        $events = $absence->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(AbsenceRegistered::class, $events[0]);
        $this->assertSame(AbsenceType::Vacation, $events[0]->type());
    }

    public function test_cancel_marks_absence_canceled_and_emits_event(): void
    {
        $absence = $this->makeAbsence('2026-05-01', '2026-05-03', AbsenceType::Vacation);
        $absence->pullDomainEvents(); // drop registered event

        $absence->cancel();

        $this->assertTrue($absence->isCanceled());
        $this->assertFalse($absence->isActive());

        $events = $absence->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(AbsenceCanceled::class, $events[0]);
    }

    public function test_cancel_is_idempotent(): void
    {
        $absence = $this->makeAbsence('2026-05-01', '2026-05-03', AbsenceType::Vacation);
        $absence->pullDomainEvents();

        $absence->cancel();
        $absence->cancel(); // second call should be no-op

        $events = $absence->pullDomainEvents();
        $this->assertCount(1, $events); // only one Canceled event
    }

    public function test_covers_date_returns_false_when_canceled(): void
    {
        $absence = $this->makeAbsence('2026-05-01', '2026-05-03', AbsenceType::Vacation);
        $inside = new DateTimeImmutable('2026-05-02');

        $this->assertTrue($absence->coversDate($inside));
        $absence->cancel();
        $this->assertFalse($absence->coversDate($inside));
    }

    public function test_period_disallows_end_before_start(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AbsencePeriod(
            new DateTimeImmutable('2026-05-03'),
            new DateTimeImmutable('2026-05-01'),
        );
    }

    public function test_period_allows_single_day(): void
    {
        $period = new AbsencePeriod(
            new DateTimeImmutable('2026-05-01'),
            new DateTimeImmutable('2026-05-01'),
        );
        $this->assertSame(1, $period->daysInclusive());
    }

    public function test_days_inclusive_counts_both_endpoints(): void
    {
        $period = new AbsencePeriod(
            new DateTimeImmutable('2026-05-01'),
            new DateTimeImmutable('2026-05-05'),
        );
        $this->assertSame(5, $period->daysInclusive());
    }

    private function makeAbsence(string $start, string $end, AbsenceType $type): Absence
    {
        return new Absence(
            new AbsenceId('01912345-0000-7000-8000-000000000001'),
            new MemberId('01912345-0000-7000-8000-000000000002'),
            new AbsencePeriod(new DateTimeImmutable($start), new DateTimeImmutable($end)),
            $type,
            'unit test',
        );
    }
}
