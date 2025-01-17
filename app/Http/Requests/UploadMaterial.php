<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadMaterial extends FormRequest
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
            'description' => 'nullable|string',
            'file' => 'required|file|mimes:pdf,doc,docx,ppt,pptx,zip',
            'material_type' => 'required|string|max:50',
            'course_id' => 'required|exists:courses,CourseID',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'The title field is required.',
            'file.required' => 'The file field is required.',
            'file.mimes' => 'The file must be a type of: pdf, doc, docx, ppt, pptx, zip.',
            'material_type.required' => 'The material type field is required.',
            'course_id.required' => 'The course ID field is required.',
            'course_id.exists' => 'The selected course ID is invalid.',
        ];
    }
}