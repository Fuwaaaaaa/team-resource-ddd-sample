<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KpiTrendRequest extends FormRequest
{
    public const ALLOWED_DAYS = [7, 30, 90];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'date' => ['required', Rule::date()->format('Y-m-d')],
            'days' => ['sometimes', 'integer', Rule::in(self::ALLOWED_DAYS)],
        ];
    }

    public function referenceDate(): string
    {
        return (string) $this->query('date');
    }

    public function days(): int
    {
        $raw = $this->query('days');

        return $raw === null ? 30 : (int) $raw;
    }
}
