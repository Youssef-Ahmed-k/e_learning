<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePassword extends FormRequest
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
            'current_password' => 'required|min:6|max:20|regex:/[A-Z]/|regex:/[0-9]/',
            'new_password' => 'required|min:6|max:20|regex:/[A-Z]/|regex:/[0-9]/|confirmed',
        ];
    }

    public function messages()
    {
        return [
            'current_password.required' => 'The current password is required.',
            'new_password.required' => 'The new password is required.',
            'new_password.min' => 'The new password must be at least 6 characters long.',
            'new_password.confirmed' => 'The new password confirmation does not match.',
        ];
    }
}