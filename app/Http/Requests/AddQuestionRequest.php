<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddQuestionRequest extends FormRequest
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
            'quiz_id' => 'required|exists:quizzes,QuizID',
            'content' => 'required|string',
            'type' => 'required|string|in:mcq,true_false',
            'marks' => 'required|integer',
            'options' => 'required_if:type,mcq|array',
            'options.*' => 'required_if:type,mcq|string',
            'correct_option' => [
                'required_if:type,mcq|string',
                'required_if:type,true_false|in:true,false,1,0',
            ],
            'image' => 'nullable|image',
        ];
    }

    public function messages(): array
    {
        return [
            'quiz_id.required' => 'The quiz ID field is required.',
            'quiz_id.exists' => 'The selected quiz ID is invalid.',
            'content.required' => 'The content field is required.',
            'type.required' => 'The type field is required.',
            'type.in' => 'The selected type is invalid.',
            'marks.required' => 'The marks field is required.',
            'options.required_if' => 'The options field is required for multiple choice questions.',
            'options.*.required_if' => 'Each option is required for multiple choice questions.',
            'correct_option.required_if' => 'The correct option field is required for multiple choice and true/false questions.',
            'correct_option.in' => 'The correct option must be true, false, 1, or 0 for true/false questions.',
            'image.image' => 'The image must be an image file.',
        ];
    }
}