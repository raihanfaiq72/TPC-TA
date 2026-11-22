<?php

use App\Http\Controllers\Api\KolamController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json([
    'status' => 'ok',
    'app' => config('app.name'),
]));

/*
| Endpoint sandbox.
| Saat ini publik dengan throttle. Setelah Sanctum terpasang, bungkus
| group ini dengan middleware('auth:sanctum') sesuai kebutuhan.
*/
Route::prefix('sandbox')
    ->middleware('throttle:30,1')
    ->name('api.sandbox.')
    ->group(function () {
        Route::post('/kolam', [KolamController::class, 'store'])
            ->name('kolam.store');
    });
