<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionRequest extends FormRequest
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
            'content' => 'sometimes|required|string',
            'type' => 'sometimes|required|string|in:mcq,true_false,short_answer',
            'marks' => 'sometimes|required|integer',
            'options' => 'sometimes|required_if:type,mcq|array',
            'options.*' => 'sometimes|required_if:type,mcq|string',
            'correct_option' => 'sometimes|required_if:type,mcq|string|required_if:type,true_false|in:true,false,1,0|required_if:type,short_answer|string',
            'image' => 'nullable|image',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'The content field is required.',
            'type.required' => 'The type field is required.',
            'type.in' => 'The selected type is invalid.',
            'marks.required' => 'The marks field is required.',
            'options.required_if' => 'The options field is required for multiple choice questions.',
            'options.*.required_if' => 'Each option is required for multiple choice questions.',
            'correct_option.required_if' => 'The correct option field is required for multiple choice, true/false, and short answer questions.',
            'image.image' => 'The image must be an image file.',
        ];
    }
}
