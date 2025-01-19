<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaterial extends FormRequest
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
            'material_id' => 'required|exists:materials,MaterialID',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:255',
            'file' => 'sometimes|file|mimes:pdf,docx,txt|max:10240',
            'video' => 'sometimes|file|mimes:mp4|max:10240',
            'material_type' => 'sometimes|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'material_id.required' => 'The material ID field is required.',
            'material_id.exists' => 'The selected material ID is invalid.',
            'title.string' => 'The title must be a string.',
            'title.max' => 'The title may not be greater than 255 characters.',
            'description.string' => 'The description must be a string.',
            'description.max' => 'The description may not be greater than 255 characters.',
            'file.file' => 'The file must be a file.',
            'file.mimes' => 'The file must be a type of: pdf, docx, txt.',
            'file.max' => 'The file may not be greater than 10240 kilobytes.',
            'video.file' => 'The video must be a file.',
            'video.mimes' => 'The video must be a type of: mp4.',
            'video.max' => 'The video may not be greater than 10240 kilobytes.',
            'material_type.string' => 'The material type must be a string.',
            'material_type.max' => 'The material type may not be greater than 50 characters.',];
    }
}