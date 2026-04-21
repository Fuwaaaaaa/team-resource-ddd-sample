<?php

declare(strict_types=1);

namespace App\Domain\Availability;

use App\Domain\Member\MemberId;

/**
 * メンバーの不在期間。Availability 集約ルート。
 *
 * 登録（= active）→ キャンセル（= canceled）の 2 状態。
 * 同一メンバーで期間が重なる不在を許容するかは Application 層で判断する。
 */
final class Absence
{
    private AbsenceId $id;
    private MemberId $memberId;
    private AbsencePeriod $period;
    private AbsenceType $type;
    private string $note;
    private bool $canceled = false;
    /** @var object[] */
    private array $domainEvents = [];

    public function __construct(
        AbsenceId $id,
        MemberId $memberId,
        AbsencePeriod $period,
        AbsenceType $type,
        string $note = ''
    ) {
        $this->id = $id;
        $this->memberId = $memberId;
        $this->period = $period;
        $this->type = $type;
        $this->note = $note;

        $this->domainEvents[] = new Events\AbsenceRegistered($id, $memberId, $period, $type);
    }

    public function id(): AbsenceId
    {
        return $this->id;
    }

    public function memberId(): MemberId
    {
        return $this->memberId;
    }

    public function period(): AbsencePeriod
    {
        return $this->period;
    }

    public function type(): AbsenceType
    {
        return $this->type;
    }

    public function note(): string
    {
        return $this->note;
    }

    public function isCanceled(): bool
    {
        return $this->canceled;
    }

    public function isActive(): bool
    {
        return !$this->canceled;
    }

    public function cancel(): void
    {
        if ($this->canceled) {
            return;
        }
        $this->canceled = true;
        $this->domainEvents[] = new Events\AbsenceCanceled($this->id, $this->memberId);
    }

    public function coversDate(\DateTimeImmutable $date): bool
    {
        return $this->isActive() && $this->period->contains($date);
    }

    /** @return object[] */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}
