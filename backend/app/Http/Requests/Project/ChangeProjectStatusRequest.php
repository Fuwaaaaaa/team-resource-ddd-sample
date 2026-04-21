<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Domain\Project\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeProjectStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $values = array_map(fn (ProjectStatus $s) => $s->value, ProjectStatus::cases());

        return [
            'status' => ['required', 'string', Rule::in($values)],
        ];
    }
}
