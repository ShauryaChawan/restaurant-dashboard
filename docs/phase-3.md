# 🍽️ Restaurant Analytics Platform
## Phase 3 — Authentication Module
### Detailed Actionable Documentation

> **Focus:** Sanctum SPA auth — Register, Login, Logout, Me endpoint, UserSeeder, and full Pest test coverage.

---

| Attribute | Decision |
|---|---|
| Auth Driver | Laravel Sanctum (SPA cookie-based) |
| Register Fields | `username`, `email`, `password`, `password_confirmation` |
| Username | Unique handle — enforced via DB unique constraint + mutation on delete |
| Email | Unique — enforced via DB unique constraint + mutation on delete |
| Soft Deletes | Enabled on `users` table — uniqueness freed via mutation strategy |
| Uniqueness Strategy | Mutate username/email on soft delete (`john` → `john_deleted_1`) |
| Password Hashing | Bcrypt (Laravel default via `hashed` model cast) |
| Post-Register Response | Message only — user must login separately |
| Default Dev Account | Created via `UserSeeder` |

---

## Table of Contents

- [🍽️ Restaurant Analytics Platform](#️-restaurant-analytics-platform)
  - [Phase 3 — Authentication Module](#phase-3--authentication-module)
    - [Detailed Actionable Documentation](#detailed-actionable-documentation)
  - [Table of Contents](#table-of-contents)
  - [1. Phase Goals \& Deliverables](#1-phase-goals--deliverables)
    - [Deliverables Checklist](#deliverables-checklist)
  - [2. Users Table Migration](#2-users-table-migration)
  - [3. User Model](#3-user-model)
  - [4. Soft Delete Uniqueness Strategy](#4-soft-delete-uniqueness-strategy)
    - [The Problem](#the-problem)
    - [The Solution — Mutate on Delete](#the-solution--mutate-on-delete)
    - [Where This Logic Lives](#where-this-logic-lives)
  - [5. Form Requests](#5-form-requests)
    - [5.1 — RegisterRequest](#51--registerrequest)
    - [5.2 — LoginRequest](#52--loginrequest)
  - [6. AuthService](#6-authservice)
  - [7. AuthController](#7-authcontroller)
  - [8. UserSeeder](#8-userseeder)
    - [Update DatabaseSeeder](#update-databaseseeder)
  - [9. API Routes — Recap](#9-api-routes--recap)
  - [10. Pest Tests — Phase 3](#10-pest-tests--phase-3)
    - [Update UserFactory](#update-userfactory)
    - [Phase 3 Test File](#phase-3-test-file)
    - [Running Phase 3 Tests](#running-phase-3-tests)
  - [11. Phase 3 Completion Checklist](#11-phase-3-completion-checklist)

---

## 1. Phase Goals & Deliverables

By the end of Phase 3 you should have a fully working auth system — register, login, logout, and a `/me` endpoint — all protected and returning consistent JSON responses via the `ApiController` base class.

### Deliverables Checklist

- [ ] `users` migration updated with `username`, `softDeletes()`, and DB-level unique constraints
- [ ] `User` model updated with `SoftDeletes`, `HasApiTokens`, `username` in `$fillable`
- [ ] `UserFactory` updated with `username` field
- [ ] `RegisterRequest` with full validation
- [ ] `LoginRequest` with validation
- [ ] `AuthService` with `register()`, `login()`, `logout()`, `deleteAccount()`
- [ ] `AuthController` extending `ApiController`
- [ ] `UserSeeder` creating a default dev account
- [ ] `DatabaseSeeder` calling `UserSeeder` first
- [ ] All Phase 3 Pest tests passing

---

## 2. Users Table Migration

> 📄 **File:** `database/migrations/0001_01_01_000000_create_users_table.php`

> 💡 **Note:** Since this project is in active development, we modify the existing migration directly rather than creating a new one. In a live production app you would always create a new migration instead.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Uniqueness strategy:
     *   Both username and email have DB-level unique constraints.
     *   Soft delete uniqueness is handled by mutating the username/email
     *   on deletion (e.g. john → john_deleted_1) rather than using
     *   composite indexes, which are broken in MySQL for NULL values.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique(); // DB-level unique — safe with mutation strategy
            $table->string('name');               // display name, kept for Laravel compatibility
            $table->string('email')->unique();    // DB-level unique — safe with mutation strategy
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->softDeletes();               // adds deleted_at column
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
```

---

## 3. User Model

> 📄 **File:** `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User Model
 *
 * Represents an authenticated user of the platform.
 *
 * Soft Deletes:
 *   Deleted users are NOT permanently removed — deleted_at is set instead.
 *   To keep the DB-level unique constraints working correctly, username
 *   and email are mutated on deletion via AuthService::deleteAccount().
 *
 *   Example:
 *     johndoe           → johndoe_deleted_1
 *     john@example.com  → john@example.com_deleted_1
 *
 * @property int         $id
 * @property string      $username
 * @property string      $name
 * @property string      $email
 * @property string      $password
 * @property \Carbon\Carbon|null $deleted_at
 * @property \Carbon\Carbon      $created_at
 * @property \Carbon\Carbon      $updated_at
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * Never expose password or remember_token in API responses.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * 'password' => 'hashed' auto-hashes via Bcrypt on assignment.
     * No need for Hash::make() anywhere in the codebase.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'deleted_at'        => 'datetime',
        'password'          => 'hashed',
    ];
}
```

---

## 4. Soft Delete Uniqueness Strategy

### The Problem

MySQL does **not** support partial indexes (`UNIQUE WHERE deleted_at IS NULL`).
Composite unique indexes on `(username, deleted_at)` are also **broken in MySQL**
because `NULL != NULL` — meaning two rows with the same username and `deleted_at = NULL`
are treated as different by the index:

| username | deleted_at |
|---|---|
| john | NULL |
| john | NULL | ← ❌ MySQL allows this — should be blocked |

App-level uniqueness checks (`whereNull('deleted_at')`) alone are also unsafe under
concurrent requests and race conditions.

### The Solution — Mutate on Delete

The correct production-grade solution for **MySQL + Laravel SoftDeletes** is to mutate
the `username` and `email` at the point of deletion, freeing them up for future
registrations while the simple DB-level unique constraint remains intact.

```
Before delete:   username = johndoe          email = john@example.com
After delete:    username = johndoe_deleted_1  email = john@example.com_deleted_1
```

This means:
- Active users are always protected by the DB unique constraint ✅
- Soft-deleted users free up their username/email for re-registration ✅
- Safe against race conditions — DB constraint is the final guard ✅
- No MySQL-specific workarounds needed ✅

> 🏭 **Production reference:** This is the pattern used by large SaaS platforms
> (Stripe, Shopify, and others) to handle soft delete uniqueness on MySQL.

### Where This Logic Lives

The mutation happens inside `AuthService::deleteAccount()` — not in the model,
not in a controller. This keeps the User model clean and the business rule
co-located with other auth logic.

---

## 5. Form Requests

### 5.1 — RegisterRequest

> 📄 **File:** `app/Http/Requests/Auth/RegisterRequest.php`

```bash
php artisan make:request Auth/RegisterRequest
```

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * RegisterRequest
 *
 * Validates incoming registration payloads.
 *
 * Uniqueness for email and username uses simple unique:users rules.
 * Safety against soft-deleted record conflicts is handled by the
 * mutation strategy in AuthService::deleteAccount(), not here.
 */
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
            'username.alpha_dash'        => 'Username may only contain letters, numbers, dashes, and underscores.',
            'username.unique'            => 'This username is already taken.',
            'email.unique'               => 'An account with this email already exists.',
            'password_confirmation.same' => 'Password confirmation does not match.',
        ];
    }
}
```

### 5.2 — LoginRequest

> 📄 **File:** `app/Http/Requests/Auth/LoginRequest.php`

```bash
php artisan make:request Auth/LoginRequest
```

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * LoginRequest
 *
 * Validates incoming login payloads.
 * Credential verification is handled in AuthService, not here.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
```

---

## 6. AuthService

> 📄 **File:** `app/Services/AuthService.php`

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * AuthService
 *
 * Handles all authentication business logic.
 * Controllers delegate to this service — they never touch
 * the User model or Hash facade directly.
 *
 * Methods:
 *   register(array $data): User
 *   login(array $credentials): string|null
 *   logout(User $user): void
 *   deleteAccount(User $user): void
 */
class AuthService
{
    /**
     * Register a new user.
     *
     * Password is NOT manually hashed here — the User model's
     * 'password' cast is set to 'hashed', so Laravel auto-hashes
     * via Bcrypt on assignment.
     *
     * @param  array $data  Validated data from RegisterRequest.
     * @return User         The newly created user.
     */
    public function register(array $data): User
    {
        return User::create([
            'username' => $data['username'],
            'name'     => $data['username'], // use username as display name
            'email'    => $data['email'],
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
     * @param  array       $credentials  ['email' => ..., 'password' => ...]
     * @return string|null               Plain-text token on success, null on failure.
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
     * @param  User $user  The currently authenticated user.
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
     * @param  User $user  The user to delete.
     */
    public function deleteAccount(User $user): void
    {
        $user->username = $user->username . '_deleted_' . $user->id;
        $user->email    = $user->email    . '_deleted_' . $user->id;
        $user->save();

        $user->delete(); // soft delete — sets deleted_at
    }
}
```

---

## 7. AuthController

> 📄 **File:** `app/Http/Controllers/Api/V1/Auth/AuthController.php`

```bash
php artisan make:controller Api/V1/Auth/AuthController
```

```php
<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * AuthController
 *
 * Thin HTTP controller for authentication endpoints.
 * All business logic is delegated to AuthService.
 *
 * Endpoints:
 *   POST /api/v1/auth/register  → register()
 *   POST /api/v1/auth/login     → login()
 *   POST /api/v1/auth/logout    → logout()   [protected]
 *   GET  /api/v1/auth/me        → me()       [protected]
 */
class AuthController extends ApiController
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Register a new user account.
     *
     * Returns a 201 message-only response.
     * User must login separately to obtain a token.
     *
     * POST /api/v1/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $this->authService->register($request->validated());

        return $this->created(
            null,
            'Registration successful. Please login to continue.'
        );
    }

    /**
     * Login and receive a Sanctum token.
     *
     * Returns the token and authenticated user object on success.
     *
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $token = $this->authService->login($request->validated());

        if (! $token) {
            return $this->error(
                'Invalid credentials. Please check your email and password.',
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $this->success([
            'token' => $token,
            'user'  => auth()->user(),
        ], 'Login successful.');
    }

    /**
     * Logout the authenticated user.
     *
     * Revokes the current Sanctum token only.
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success(null, 'Logged out successfully.');
    }

    /**
     * Return the currently authenticated user.
     *
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success($request->user(), 'Authenticated user fetched.');
    }
}
```

---

## 8. UserSeeder

> 📄 **File:** `database/seeders/UserSeeder.php`

```bash
php artisan make:seeder UserSeeder
```

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * UserSeeder
 *
 * Creates a default admin/dev account for local development.
 * Used to login and test the dashboard locally without going
 * through the registration flow each time.
 *
 * Credentials:
 *   Email    : admin@restaurant.dev
 *   Password : Password@123
 *   Username : admin
 *
 * IMPORTANT: Never seed real credentials. Always use clearly
 * dummy dev-only values that are not production passwords.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Avoid duplicate seeding on re-runs
        if (User::where('email', 'admin@restaurant.dev')->exists()) {
            $this->command->info('⚠️  Admin user already exists — skipping.');
            return;
        }

        User::create([
            'username' => 'admin',
            'name'     => 'admin',
            'email'    => 'admin@restaurant.dev',
            'password' => 'Password@123', // auto-hashed by model cast
        ]);

        $this->command->info('✅ Default admin user seeded.');
        $this->command->info('   Email    : admin@restaurant.dev');
        $this->command->info('   Password : Password@123');
    }
}
```

### Update DatabaseSeeder

> 📄 **File:** `database/seeders/DatabaseSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DatabaseSeeder
 *
 * Orchestrates all seeders in dependency order.
 *
 * Order matters:
 *   1. UserSeeder       — no dependencies
 *   2. RestaurantSeeder — no dependencies
 *   3. OrderSeeder      — depends on RestaurantSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,        // default admin account
            RestaurantSeeder::class,  // must run before OrderSeeder
            OrderSeeder::class,       // depends on restaurants being seeded
        ]);
    }
}
```

---

## 9. API Routes — Recap

No changes needed to `routes/api.php`. All auth routes were defined in Phase 1.

```php
Route::prefix('v1')->group(function () {

    // ── Public ----------------------------------------───────────
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login',    [AuthController::class, 'login']);
    });

    // ── Protected (Sanctum) ----------------------------------------──────
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me',      [AuthController::class, 'me']);
    });

});
```

---

## 10. Pest Tests — Phase 3

### Update UserFactory

Before running tests, update `UserFactory` to include the `username` field.

> 📄 **File:** `database/factories/UserFactory.php`

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'username'          => $this->faker->unique()->userName(),
            'name'              => $this->faker->name(),
            'email'             => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => 'Password@123', // auto-hashed by model cast
            'remember_token'    => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
```

### Phase 3 Test File

> 📄 **File:** `tests/Feature/Phase3/AuthTest.php`

```php
<?php

use App\Models\User;
use App\Services\AuthService;

describe('Phase 3 — Authentication', function () {

    // ── Registration ----------------------------------------─────

    it('registers a new user with valid credentials', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'username'              => 'johndoe',
            'email'                 => 'john@example.com',
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status'  => 'success',
            'message' => 'Registration successful. Please login to continue.',
            'data'    => null,
        ]);

        $this->assertDatabaseHas('users', [
            'email'    => 'john@example.com',
            'username' => 'johndoe',
        ]);
    });

    it('fails registration with duplicate email', function () {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'username'              => 'johndoe2',
            'email'                 => 'john@example.com',
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['status' => 'error', 'message' => 'Validation failed']);
        $response->assertJsonPath('errors.email.0', 'An account with this email already exists.');
    });

    it('fails registration with duplicate username', function () {
        User::factory()->create(['username' => 'johndoe']);

        $response = $this->postJson('/api/v1/auth/register', [
            'username'              => 'johndoe',
            'email'                 => 'different@example.com',
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.username.0', 'This username is already taken.');
    });

    it('fails registration when password confirmation does not match', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'username'              => 'johndoe',
            'email'                 => 'john@example.com',
            'password'              => 'Password@123',
            'password_confirmation' => 'WrongPassword@123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.password_confirmation.0', 'Password confirmation does not match.');
    });

    it('fails registration with weak password', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'username'              => 'johndoe',
            'email'                 => 'john@example.com',
            'password'              => '12345678',
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
            'email'    => 'john@example.com',
            'username' => 'johndoe',
        ]);

        // Simulate deleteAccount mutation
        app(AuthService::class)->deleteAccount($user);

        // Same email should now be available
        $response = $this->postJson('/api/v1/auth/register', [
            'username'              => 'johndoe_new',
            'email'                 => 'john@example.com',
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(201);
    });

    // ── Login ----------------------------------------────────────

    it('logs in with correct credentials and returns a token', function () {
        User::factory()->create([
            'email'    => 'john@example.com',
            'password' => 'Password@123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'john@example.com',
            'password' => 'Password@123',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success', 'message' => 'Login successful.']);
        $response->assertJsonStructure(['data' => ['token', 'user']]);
    });

    it('fails login with wrong password', function () {
        User::factory()->create([
            'email'    => 'john@example.com',
            'password' => 'Password@123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'john@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['status' => 'error']);
    });

    it('fails login with non-existent email', function () {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'Password@123',
        ]);

        $response->assertStatus(401);
    });

    it('fails login with missing fields', function () {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['email', 'password']]);
    });

    // ── Me ----------------------------------------───────────────

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

    // ── Logout ----------------------------------------───────────

    it('logs out and invalidates the token', function () {
        $user  = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success', 'message' => 'Logged out successfully.']);

        // Token should now be invalid
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    });

    it('returns 401 on logout when not authenticated', function () {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    });

    // ── Delete Account (Mutation Strategy) ────────────────────────────

    it('deleteAccount mutates username and email before soft deleting', function () {
        $user = User::factory()->create([
            'username' => 'johndoe',
            'email'    => 'john@example.com',
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

    // ── UserSeeder ----------------------------------------───────

    it('UserSeeder creates the default admin account', function () {
        $this->seed(\Database\Seeders\UserSeeder::class);

        $this->assertDatabaseHas('users', [
            'email'    => 'admin@restaurant.dev',
            'username' => 'admin',
        ]);
    });

    it('UserSeeder does not duplicate admin on re-run', function () {
        $this->seed(\Database\Seeders\UserSeeder::class);
        $this->seed(\Database\Seeders\UserSeeder::class);

        expect(User::where('email', 'admin@restaurant.dev')->count())->toBe(1);
    });

});
```

### Running Phase 3 Tests

```bash
# Run Phase 3 tests only
php artisan test --filter Phase3

# Run full suite — ensure nothing broken
php artisan test
```

---

## 11. Phase 3 Completion Checklist

| Item | Status |
|---|---|
| `users` migration updated with `username`, `softDeletes()`, DB unique constraints | ☐ |
| `php artisan migrate:fresh --seed` runs without errors | ☐ |
| `User` model with `SoftDeletes`, `HasApiTokens`, `username` in `$fillable` | ☐ |
| `password` cast set to `hashed` in User model | ☐ |
| `UserFactory` updated with `username` field | ☐ |
| `RegisterRequest` with simple `unique:users` rules | ☐ |
| `LoginRequest` with email + password validation | ☐ |
| `AuthService` with `register()`, `login()`, `logout()`, `deleteAccount()` | ☐ |
| `deleteAccount()` mutates username/email before soft delete | ☐ |
| `AuthController` extending `ApiController` | ☐ |
| `UserSeeder` with default admin account | ☐ |
| `DatabaseSeeder` calls `UserSeeder` first | ☐ |
| All Phase 3 Pest tests passing | ☐ |
| Full test suite (`php artisan test`) still green | ☐ |

---

*End of Phase 3 Documentation • Next: Phase 4 — Restaurant Module*
