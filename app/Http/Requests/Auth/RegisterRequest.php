<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * All users can attempt to register.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for registration.
     */
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'alpha_dash',             // only letters, numbers, dashes, underscores
                'unique:users,username',  // DB-level check — safe due to mutation strategy
            ],
            'name' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'alpha_dash',             // only letters, numbers, dashes, underscores
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',     // DB-level check — safe due to mutation strategy
            ],
            'password' => [
                'required',
                'string',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers(),
            ],
            'password_confirmation' => [
                'required',
                'same:password',          // must match password field exactly
            ],
        ];
    }

    /**
     * Custom validation error messages.
     */
    public function messages(): array
    {
        return [
            'username.required' => 'Username is required.',
            'username.string' => 'Username must be a valid string.',
            'username.min' => 'Username must be at least 3 characters.',
            'username.max' => 'Username must not exceed 30 characters.',
            'username.alpha_dash' => 'Username may only contain letters, numbers, dashes, and underscores.',
            'username.unique' => 'This username is already taken.',
            'name.required' => 'Name is required.',
            'name.string' => 'Name must be a valid string.',
            'name.min' => 'Name must be at least 3 characters.',
            'name.max' => 'Name must not exceed 30 characters.',
            'name.alpha_dash' => 'Name may only contain letters, numbers, dashes, and underscores.',
            'email.required' => 'Email is required.',
            'email.string' => 'Email must be a valid string.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'Email must not exceed 255 characters.',
            'email.unique' => 'An account with this email already exists.',
            'password.required' => 'Password is required.',
            'password.string' => 'Password must be a valid string.',
            'password_confirmation.required' => 'Password confirmation is required.',
            'password_confirmation.same' => 'Password confirmation does not match.',
        ];
    }
}
