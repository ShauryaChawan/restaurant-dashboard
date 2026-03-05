<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    /**
     * Register a new user.
     *
     * Password is NOT manually hashed here — the User model's
     * 'password' cast is set to 'hashed', so Laravel auto-hashes
     * via Bcrypt on assignment.
     *
     * @param  array  $data  Validated data from RegisterRequest.
     * @return User The newly created user.
     */
    public function register(array $data): User
    {
        return User::create([
            'username' => $data['username'],
            'name' => $data['username'], // use username as display name
            'email' => $data['email'],
            'password' => $data['password'], // auto-hashed by model cast
        ]);
    }

    /**
     * Attempt to login a user and return a Sanctum token.
     *
     * Uses Laravel's Auth::attempt() which handles Bcrypt
     * verification internally.
     *
     * Single-session enforcement:
     *   All previous tokens are revoked before issuing a new one.
     *   Remove $user->tokens()->delete() to allow multiple concurrent sessions.
     *
     * @param  array  $credentials  ['email' => ..., 'password' => ...]
     * @return string|null Plain-text token on success, null on failure.
     */
    public function login(array $credentials): ?string
    {
        if (! Auth::attempt($credentials)) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        // Revoke all previous tokens — single-session enforcement
        $user->tokens()->delete();

        return $user->createToken('auth_token')->plainTextToken;
    }

    /**
     * Logout the authenticated user by revoking their current token.
     *
     * Only the current token is revoked — other device sessions remain active.
     * To revoke ALL tokens use: $user->tokens()->delete()
     *
     * @param  User  $user  The currently authenticated user.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Soft delete a user account.
     *
     * Mutation strategy:
     *   Before soft deleting, username and email are mutated by appending
     *   '_deleted_{id}' to free them up for future registrations while
     *   keeping the DB-level unique constraints intact.
     *
     *   Example:
     *     johndoe          → johndoe_deleted_1
     *     john@example.com → john@example.com_deleted_1
     *
     * @param  User  $user  The user to delete.
     */
    public function deleteAccount(User $user): void
    {
        $user->username = $user->username.'_deleted_'.$user->id;
        $user->email = $user->email.'_deleted_'.$user->id;
        $user->save();

        $user->delete(); // soft delete — sets deleted_at
    }
}
