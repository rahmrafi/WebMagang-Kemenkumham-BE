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
            'period_id' => [
                Rule::requiredIf(fn () => $this->input('type') === 'magang'),
                'nullable',
                'integer',
                'exists:internship_periods,id',
            ],
            'institution' => ['required', 'string', 'max:150'],
            'campus_city' => ['required', 'string', 'max:100'],
            'study_program' => ['required', 'string', 'max:100'],
            'education_level' => ['required', 'string', Rule::in(['SMA', 'SMK', 'D3', 'D4', 'S1'])],
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
            'member_4' => ['prohibited_if:type,magang', 'nullable', 'string', 'max:100'],
            'member_5' => ['prohibited_if:type,magang', 'nullable', 'string', 'max:100'],
            'member_6' => ['prohibited_if:type,magang', 'nullable', 'string', 'max:100'],
            'member_7' => ['prohibited_if:type,magang', 'nullable', 'string', 'max:100'],
            'member_8' => ['prohibited_if:type,magang', 'nullable', 'string', 'max:100'],
            'member_9' => ['prohibited_if:type,magang', 'nullable', 'string', 'max:100'],
            'member_10' => ['prohibited_if:type,magang', 'nullable', 'string', 'max:100'],
            'letter_number' => ['required', 'string', 'max:100'],
            'letter_date'   => ['required', 'date'],
            'phone_number' => ['required', 'string', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'document' => ['required', 'file', 'mimes:zip', 'max:10240'],
        ];
    }
}
