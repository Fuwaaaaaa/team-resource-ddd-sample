<?php

declare(strict_types=1);

namespace App\Http\Requests\Note;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'entityType' => ['required', Rule::in(['member', 'project', 'allocation'])],
            'entityId' => ['required', 'uuid'],
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }
}
