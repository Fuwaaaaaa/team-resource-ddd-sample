<?php

declare(strict_types=1);

namespace App\Domain\Availability;

/**
 * 不在の種類。
 *
 * vacation : 有給休暇
 * sick     : 病気/体調不良
 * holiday  : 会社指定休日 / 祝日振替
 * training : 研修・教育（稼働としてはカウントしない）
 * other    : その他
 */
enum AbsenceType: string
{
    case Vacation = 'vacation';
    case Sick = 'sick';
    case Holiday = 'holiday';
    case Training = 'training';
    case Other = 'other';
}
