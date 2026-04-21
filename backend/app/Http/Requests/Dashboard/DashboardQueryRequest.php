<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class DashboardQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function referenceDate(): string
    {
        return (string) $this->query('date');
    }
}
