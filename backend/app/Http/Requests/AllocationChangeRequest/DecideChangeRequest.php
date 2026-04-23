<?php

declare(strict_types=1);

namespace App\Http\Requests\AllocationChangeRequest;

use Illuminate\Foundation\Http\FormRequest;

class DecideChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
