<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['magang', 'penelitian'])],
            'position_id' => [
                Rule::requiredIf(fn () => $this->input('type') === 'magang'),
                'nullable',
                'integer',
                'exists:internship_positions,id',
            ],
            'institution' => ['required', 'string', 'max:150'],
            'study_program' => ['required', 'string', 'max:100'],
            'research_title' => [
                Rule::requiredIf(fn () => $this->input('type') === 'penelitian'),
                'nullable',
                'string',
                'max:255',
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'member_1' => ['required', 'string', 'max:100'],
            'member_2' => ['nullable', 'string', 'max:100'],
            'member_3' => ['nullable', 'string', 'max:100'],
            'letter_number' => ['required', 'string', 'max:100'],
            'phone_number' => ['required', 'string', 'max:20'],
            'document' => ['required', 'file', 'mimes:zip', 'max:10240'],
        ];
    }
}
