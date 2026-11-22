<?php

use App\Http\Controllers\Api\KolamController;
use App\Http\Controllers\Api\LingkunganController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json([
    'status' => 'ok',
    'app' => config('app.name'),
]));

Route::middleware('throttle:30,1')->group(function () {

    Route::prefix('kolam')
        ->name('api.kolam.')
        ->controller(KolamController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{id}', 'show')->whereNumber('id')->name('show');
            Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
        });

    Route::prefix('kolam/{kolamId}/lingkungan')
        ->name('api.kolam.lingkungan.')
        ->controller(LingkunganController::class)
        ->group(function () {
            Route::get('/', 'show')->whereNumber('kolamId')->name('show');
            Route::post('/tick', 'tick')->whereNumber('kolamId')->name('tick');
            Route::post('/amonia', 'amonia')->whereNumber('kolamId')->name('amonia');
            Route::post('/sisa-pakan', 'sisaPakan')->whereNumber('kolamId')->name('sisaPakan');
        });

    // Alias
    Route::prefix('sandbox/kolam')
        ->name('api.sandbox.kolam.')
        ->controller(KolamController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{id}', 'show')->whereNumber('id')->name('show');
        });
});
