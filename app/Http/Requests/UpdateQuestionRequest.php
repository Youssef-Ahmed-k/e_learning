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
            'type' => 'sometimes|required|string|in:mcq,true_false',
            'marks' => 'sometimes|required|integer',
            'options' => 'sometimes|required_if:type,mcq|array',
            'options.*' => 'sometimes|required_if:type,mcq|string',
            'correct_option' => [
                'sometimes',
                'required_if:type,mcq',
                'required_if:type,true_false',
            ],
            'image' => 'nullable|image',
        ];
    }

    public function withValidator($validator)
    {
        $validator->sometimes('correct_option', 'in:true,false,1,0,True,False', function ($input) {
            return $input->type === 'true_false';
        });

        $validator->sometimes('correct_option', 'string', function ($input) {
            return $input->type === 'mcq' ;
        });

        $validator->sometimes('correct_option', 'in:' . implode(',', $this->input('options', [])), function ($input) {
            return $input->type === 'mcq';
        });
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
            'correct_option.required_if' => 'The correct option field is required for :type questions.',
            'correct_option.in' => 'The correct option must be one of the allowed values for true/false questions.',
            'image.image' => 'The image must be an image file.',
        ];
    }
}