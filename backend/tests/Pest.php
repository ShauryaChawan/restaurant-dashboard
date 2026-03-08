<?php

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

// Helper: create and authenticate a user
function actingAsUser(): Tests\TestCase
{
    $user = \App\Models\User::factory()->create();

    return test()->actingAs($user);
}
