<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SkillGapQueryRequest extends FormRequest
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
            'projectId' => ['nullable', 'uuid'],
        ];
    }

    public function referenceDate(): string
    {
        return (string) $this->query('date');
    }

    public function projectId(): ?string
    {
        $value = $this->query('projectId');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
