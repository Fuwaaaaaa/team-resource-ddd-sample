<?php

declare(strict_types=1);

namespace App\Http\Requests\AllocationChangeRequest;

use App\Domain\AllocationChangeRequest\ChangeRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $type = (string) $this->input('type');
        $payloadRules = match ($type) {
            ChangeRequestType::CreateAllocation->value => [
                'payload.memberId' => ['required', 'uuid'],
                'payload.projectId' => ['required', 'uuid'],
                'payload.skillId' => ['required', 'uuid'],
                'payload.allocationPercentage' => ['required', 'integer', 'between:1,100'],
                'payload.periodStart' => ['required', Rule::date()->format('Y-m-d')],
                'payload.periodEnd' => ['required', Rule::date()->format('Y-m-d')->after('payload.periodStart')],
            ],
            ChangeRequestType::RevokeAllocation->value => [
                'payload.allocationId' => ['required', 'uuid'],
            ],
            default => [],
        };

        return array_merge([
            'type' => ['required', Rule::in([
                ChangeRequestType::CreateAllocation->value,
                ChangeRequestType::RevokeAllocation->value,
            ])],
            'payload' => ['required', 'array'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], $payloadRules);
    }
}
