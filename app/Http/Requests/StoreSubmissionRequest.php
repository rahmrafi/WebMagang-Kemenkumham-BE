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
            'institution' => ['required', 'string', 'max:150', 'regex:/^[a-zA-Z0-9\s.,\'()\-]+$/'],
            'campus_city' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9\s.,\'()\-]+$/'],
            'study_program'   => [
                // Wajib hanya untuk jenjang perkuliahan; opsional untuk SMA/SMK dan umum/profesional/dosen.
                Rule::requiredIf(fn () => in_array($this->input('education_level'), ['D2', 'D3', 'D4', 'S1', 'S2', 'S3'])),
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9\s.,\'()\-]+$/',
            ],
            'education_level' => ['required', 'string', Rule::in(['SMA', 'SMK', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3', 'Umum/Profesional/Dosen'])],
            'research_title' => [
                Rule::requiredIf(fn () => $this->input('type') === 'penelitian'),
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s.,\'()\-:"!?]+$/',
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'member_1' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z\s.,\']+\|[a-zA-Z0-9\s.\/-]+\|[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'],
            'member_2' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s.,\']+\|[a-zA-Z0-9\s.\/-]+$/'],
            'member_3' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s.,\']+\|[a-zA-Z0-9\s.\/-]+$/'],
            'member_4' => ['prohibited_if:type,magang', 'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s.,\']+\|[a-zA-Z0-9\s.\/-]+$/'],
            'member_5' => ['prohibited_if:type,magang', 'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s.,\']+\|[a-zA-Z0-9\s.\/-]+$/'],
            'member_6' => ['prohibited_if:type,magang', 'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s.,\']+\|[a-zA-Z0-9\s.\/-]+$/'],
            'member_7' => ['prohibited_if:type,magang', 'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s.,\']+\|[a-zA-Z0-9\s.\/-]+$/'],
            'member_8' => ['prohibited_if:type,magang', 'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s.,\']+\|[a-zA-Z0-9\s.\/-]+$/'],
            'member_9' => ['prohibited_if:type,magang', 'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s.,\']+\|[a-zA-Z0-9\s.\/-]+$/'],
            'member_10' => ['prohibited_if:type,magang', 'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s.,\']+\|[a-zA-Z0-9\s.\/-]+$/'],
            'letter_number' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9\s.\/-]+$/'],
            'letter_date'   => ['required', 'date'],
            'phone_number' => ['required', 'string', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'document' => ['required', 'file', 'extensions:zip', 'max:10240'],
        ];
    }
}
