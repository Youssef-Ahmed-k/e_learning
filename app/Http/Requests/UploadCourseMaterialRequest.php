<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCourseMaterialRequest extends FormRequest
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

            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'file' => 'nullable|file|mimes:pdf,docx,txt|max:10240',
            'video' => 'nullable|file|mimes:mp4|max:10240',
            'material_type' => 'required|string|in:pdf,video,text',
            'course_id' => 'required|integer|exists:courses,CourseID',

        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'The title is required.',
            'material_type.in' => 'The material type must be pdf, video, or text.',
        ];
    }
}