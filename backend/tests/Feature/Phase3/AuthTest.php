<?php

use App\Models\User;
use App\Services\AuthService;

describe('Phase 3 — Authentication', function () {

    // ── Registration ───────────────────────────────────────────────────

    it('registers a new user with valid credentials', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Registration successful. Please login to continue.',
            'data' => null,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'username' => 'johndoe',
        ]);
    });

    it('fails registration with duplicate email', function () {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'johndoe2',
            'email' => 'john@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['status' => 'error', 'message' => 'Validation failed']);
        $response->assertJsonPath('errors.email.0', 'An account with this email already exists.');
    });

    it('fails registration with duplicate username', function () {
        User::factory()->create(['username' => 'johndoe']);

        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'johndoe',
            'email' => 'different@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.username.0', 'This username is already taken.');
    });

    it('fails registration when password confirmation does not match', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'WrongPassword@123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.password_confirmation.0', 'Password confirmation does not match.');
    });

    it('fails registration with weak password', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'error');
    });

    it('fails registration with missing fields', function () {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422);
        $response->assertJsonStructure(['status', 'message', 'errors' => [
            'username', 'email', 'password',
        ]]);
    });

    it('allows registration after soft-deleted user frees up email via mutation', function () {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'username' => 'johndoe',
        ]);

        // Simulate deleteAccount mutation
        app(AuthService::class)->deleteAccount($user);

        // Same email should now be available
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'johndoe_new',
            'email' => 'john@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(201);
    });

    // ── Login --------------------------------------------------─────

    it('logs in with correct credentials and returns a token', function () {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'Password@123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'Password@123',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success', 'message' => 'Login successful.']);
        $response->assertJsonStructure(['data' => ['token', 'user']]);
    });

    it('fails login with wrong password', function () {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'Password@123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['status' => 'error']);
    });

    it('fails login with non-existent email', function () {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'Password@123',
        ]);

        $response->assertStatus(401);
    });

    it('fails login with missing fields', function () {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['email', 'password']]);
    });

    // ── Me --------------------------------------------------────────

    it('returns authenticated user on /me endpoint', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
        $response->assertJsonPath('data.email', $user->email);
        $response->assertJsonPath('data.username', $user->username);
    });

    it('returns 401 on /me when unauthenticated', function () {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
        $response->assertJson(['status' => 'error', 'message' => 'Unauthenticated']);
    });

    // ── Logout --------------------------------------------------────

    it('logs out and invalidates the token', function () {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success', 'message' => 'Logged out successfully.']);

        // Verify the token was actually deleted from the database
        expect($user->tokens()->count())->toBe(0);
    });

    it('returns 401 on logout when not authenticated', function () {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    });

    // ── Delete Account (Mutation Strategy) ────────────────────────────

    it('deleteAccount mutates username and email before soft deleting', function () {
        $user = User::factory()->create([
            'username' => 'johndoe',
            'email' => 'john@example.com',
        ]);

        $id = $user->id;

        app(AuthService::class)->deleteAccount($user);

        $user->refresh();

        expect($user->username)->toBe("johndoe_deleted_{$id}");
        expect($user->email)->toBe("john@example.com_deleted_{$id}");
        expect($user->deleted_at)->not->toBeNull();
    });

    it('deleted user does not appear in normal queries', function () {
        $user = User::factory()->create(['email' => 'john@example.com']);

        app(AuthService::class)->deleteAccount($user);

        expect(User::where('email', 'john@example.com')->exists())->toBeFalse();
    });

    // ── UserSeeder --------------------------------------------------

    it('UserSeeder creates the default admin account', function () {
        $this->seed(\Database\Seeders\UserSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@restaurant.dev',
            'username' => 'admin',
        ]);
    });

    it('UserSeeder does not duplicate admin on re-run', function () {
        $this->seed(\Database\Seeders\UserSeeder::class);
        $this->seed(\Database\Seeders\UserSeeder::class);

        expect(User::where('email', 'admin@restaurant.dev')->count())->toBe(1);
    });

});
