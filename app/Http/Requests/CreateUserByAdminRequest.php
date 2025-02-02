<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateUserByAdminRequest extends FormRequest
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
            'name' => 'required|string|max:190',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|max:20|confirmed|regex:/[A-Z]/|regex:/[0-9]/',
            'phone' => 'required|regex:/^01[0125][0-9]{8}$/|unique:users,phone',
            'address' => 'required|string|max:255',
            'role' => 'required|in:user,admin,professor',
        ];
    }

    public function attributes()
    {
        return [
            'name.required' => 'The name field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'The password field is required.',
            'password.min' => 'The password must be at least 6 characters.',
            'password.regex' => 'The password must contain at least one uppercase letter and one number.',
            'password.confirmed' => 'Password confirmation does not match.',
            'phone.required' => 'The phone number field is required.',
            'phone.regex' => 'The phone number must be a valid.',
            'phone.unique' => 'This phone number is already registered.',
            'address.required' => 'The address field is required.',
            'address.max' => 'The address must not exceed 255 characters.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();

        $errorMessages = $errors->all();

        throw new HttpResponseException(response()->json([
            'message' => $errorMessages[0],  
        ], 422));
    }
}