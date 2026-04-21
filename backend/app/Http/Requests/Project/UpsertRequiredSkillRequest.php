<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class UpsertRequiredSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'requiredProficiency' => ['required', 'integer', 'between:1,5'],
            'headcount' => ['required', 'integer', 'min:1'],
        ];
    }
}
