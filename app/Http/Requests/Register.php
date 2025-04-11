<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class Register extends FormRequest
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
            'password' => [
                'required',
                'min:6',
                'max:20',
                'confirmed',
                'regex:/^(?=.*[A-Z])(?=.*[0-9]).{6,20}$/'
            ],
            'phone' => 'required|regex:/^01[0125][0-9]{8}$/|unique:users,phone',
            'address' => 'required|string|max:255',
            'captured_images' => 'required|array|min:3', // Ensure at least 3 images
            'captured_images.*' => [
                'string',
                'regex:/^data:image\/(png|jpg|jpeg);base64,[A-Za-z0-9+\/=]+$/'
            ],
        ];
    }
    /**
     * Customize attribute names for error messages.
     */
    public function attributes()
    {
        return [
            'name' => 'name',
            'email' => 'email',
            'password' => 'password',
            'phone' => 'phone number',
            'address' => 'address',
            'captured_images' => 'captured images',
        ];
    }

    /**
     * Customize validation error messages.
     */
    public function messages()
    {
        return [
            'name.required' => 'The name field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'The password field is required.',
            'password.min' => 'The password must be at least 6 characters.',
            'password.max' => 'The password must not exceed 20 characters.',
            'password.regex' => 'The password must contain at least one uppercase letter and one number.',
            'password.confirmed' => 'Password confirmation does not match.',
            'phone.required' => 'The phone number field is required.',
            'phone.regex' => 'The phone number must be a valid Egyptian phone number.',
            'phone.unique' => 'This phone number is already registered.',
            'address.required' => 'The address field is required.',
            'address.max' => 'The address must not exceed 255 characters.',
            'captured_images.required' => 'Please capture at least 3 images.',
            'captured_images.array' => 'Captured images must be provided as an array.',
            'captured_images.min' => 'Please capture at least 3 images.',
            'captured_images.*.string' => 'Each captured image must be a valid string.',
            'captured_images.*.regex' => 'Each captured image must be a valid base64-encoded PNG, JPG, or JPEG image.',
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
