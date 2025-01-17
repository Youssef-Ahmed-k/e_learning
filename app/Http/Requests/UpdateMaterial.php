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
            'description' => 'sometimes|string',
            'file' => 'sometimes|file|mimes:pdf,doc,docx,ppt,pptx,zip',
            'material_type' => 'sometimes|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'material_id.required' => 'The material ID field is required.',
            'material_id.exists' => 'The selected material ID is invalid.',
            'title.string' => 'The title must be a string.',
            'description.string' => 'The description must be a string.',
            'file.mimes' => 'The file must be a type of: pdf, doc, docx, ppt, pptx, zip.',
            'material_type.string' => 'The material type must be a string.',
        ];
    }
}