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
            'username.alpha_dash' => 'Username may only contain letters, numbers, dashes, and underscores.',
            'username.unique' => 'This username is already taken.',
            'email.unique' => 'An account with this email already exists.',
            'password_confirmation.same' => 'Password confirmation does not match.',
        ];
    }
}
