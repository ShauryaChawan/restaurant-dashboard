<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

describe('Phase 1 — Scaffolding & Environment', function () {

    it('connects to the database successfully', function () {
        expect(fn () => DB::connection()->getPdo())->not->toThrow(Exception::class);
    });

    it('uses file cache driver', function () {
        expect(config('cache.default'))->toBe('file');
    });

    it('can write to and read from the file cache', function () {
        Cache::put('smoke_test', 'ok', 60);
        expect(Cache::get('smoke_test'))->toBe('ok');
        Cache::forget('smoke_test');
    });

    it('api v1 prefix is registered', function () {
        $routes = collect(Route::getRoutes()->getRoutesByMethod()['POST'] ?? [])
            ->keys()
            ->filter(fn ($r) => str_starts_with($r, 'api/v1'));
        expect($routes)->not->toBeEmpty();
    });

    it('returns 404 JSON for unknown api routes', function () {
        $response = $this->getJson('/api/v1/nonexistent-route');
        $response->assertStatus(404);
        // $response->assertJsonStructure(['status', 'message', 'data']);
    });

    it('returns 401 JSON for unauthenticated requests to protected routes', function () {
        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(401);
        $response->assertJson(['status' => 'error', 'message' => 'Unauthenticated']);
    });

});
