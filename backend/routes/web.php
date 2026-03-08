<?php

use Illuminate\Support\Facades\Route;

Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api).*$');

// Route::get('/', function () {
//     return view('welcome');
// });
