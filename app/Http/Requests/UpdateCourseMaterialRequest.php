<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseMaterialRequest extends FormRequest
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
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:255',
            'file' => 'sometimes|file|mimes:pdf,docx,txt|max:10240',
            'video' => 'sometimes|file|mimes:mp4|max:10240',
            'material_type' => 'sometimes|string|in:pdf,video,text',
        ];
    }

    public function messages()
    {
        return [
            'title.string' => 'The title must be a string.',
            'material_type.in' => 'The material type must be pdf, video, or text.',
        ];
    }
}