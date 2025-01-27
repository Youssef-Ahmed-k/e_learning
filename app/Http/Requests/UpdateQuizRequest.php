<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuizRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'quiz_date' => 'nullable|date',
            'course_id' => 'nullable|exists:courses,CourseID',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'The title field is required.',
            'title.string' => 'The title must be a string.',
            'title.max' => 'The title must not exceed 255 characters.',
            'description.string' => 'The description must be a string.',
            'start_time.required' => 'The start time field is required.',
            'start_time.date_format' => 'The start time must be in the format HH:MM.',
            'end_time.required' => 'The end time field is required.',
            'end_time.date_format' => 'The end time must be in the format HH:MM.',
            'end_time.after' => 'The end time must be after the start time.',
            'quiz_date.required' => 'The quiz date field is required.',
            'quiz_date.date' => 'The quiz date must be a valid date.',
            'course_id.required' => 'The course ID field is required.',
            'course_id.exists' => 'The selected course ID is invalid.',
        ];
    }
}