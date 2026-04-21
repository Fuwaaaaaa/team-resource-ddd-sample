<?php

declare(strict_types=1);

namespace App\Http\Requests\Availability;

use App\Domain\Availability\AbsenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $types = array_map(fn (AbsenceType $t) => $t->value, AbsenceType::cases());

        return [
            'memberId' => ['required', 'string', 'uuid'],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'type' => ['required', 'string', Rule::in($types)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
