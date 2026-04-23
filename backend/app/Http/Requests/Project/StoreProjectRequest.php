<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:100'],
            // 計画期間は任意。両方渡すか両方省略する運用。片方のみは required_with で弾く。
            'plannedStartDate' => ['nullable', 'required_with:plannedEndDate', Rule::date()->format('Y-m-d')],
            'plannedEndDate' => ['nullable', 'required_with:plannedStartDate', Rule::date()->format('Y-m-d')->after('plannedStartDate')],
        ];
    }
}
