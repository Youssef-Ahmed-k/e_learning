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
        $fileRules = ['nullable', 'file', 'max:10240'];

        if ($this->material_type === 'pdf') {
            $fileRules[] = 'mimes:pdf,docx,txt,ppt,pptx';
        } elseif ($this->material_type === 'video') {
            $fileRules[] = 'mimes:mp4';
        }

        return [
            'title' => 'required|string|max:255',
            'description' => $this->material_type === 'text' ? 'required|string|max:1000' : 'sometimes|nullable|string|max:255',
            'file' => $this->material_type !== 'text' ? $fileRules : 'nullable',
            'video' => $this->material_type !== 'text' ? ['nullable', 'file', 'mimes:mp4', 'max:10240'] : 'nullable',
            'material_type' => 'required|string|in:pdf,video,text',
            'course_id' => 'required|integer|exists:courses,CourseID',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->material_type === 'pdf') {
                if (!$this->hasFile('file')) {
                    $validator->errors()->add('file', 'You must upload a file when the material type is pdf.');
                }
            }

            if ($this->material_type === 'video') {
                if (!$this->hasFile('video')) {
                    $validator->errors()->add('video', 'You must upload a video when the material type is video.');
                }
            }
        });
    }

    public function messages()
    {
        return [
            'title.required' => 'The title is required.',
            'material_type.in' => 'The material type must be pdf, video, or text.',
            'file.mimes' => 'The file must be one of the following types: pdf, docx, txt, ppt, pptx.',
        ];
    }
}