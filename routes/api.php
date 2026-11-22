<?php

use App\Http\Controllers\Api\KolamController;
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
            Route::delete('/{id}','destroy')->whereNumber('id')->name('destroy');
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
