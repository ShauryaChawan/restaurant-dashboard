<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['message' => 'Restaurant Dashboard API Configuration Server is Live.'];
});
