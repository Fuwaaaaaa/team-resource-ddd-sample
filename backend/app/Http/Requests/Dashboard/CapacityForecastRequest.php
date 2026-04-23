<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CapacityForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'date' => ['required', Rule::date()->format('Y-m-d')],
            'months' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function referenceDate(): string
    {
        return (string) $this->query('date');
    }

    public function monthsAhead(): int
    {
        $raw = $this->query('months');

        return $raw === null ? 6 : (int) $raw;
    }
}
