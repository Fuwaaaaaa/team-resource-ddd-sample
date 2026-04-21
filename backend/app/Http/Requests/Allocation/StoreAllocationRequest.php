<?php

declare(strict_types=1);

namespace App\Http\Requests\Allocation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'memberId' => ['required', 'uuid'],
            'projectId' => ['required', 'uuid'],
            'skillId' => ['required', 'uuid'],
            'allocationPercentage' => ['required', 'integer', 'between:1,100'],
            'periodStart' => ['required', Rule::date()->format('Y-m-d')],
            'periodEnd' => ['required', Rule::date()->format('Y-m-d')->after('periodStart')],
            'dryRun' => ['sometimes', 'boolean'],
        ];
    }
}
