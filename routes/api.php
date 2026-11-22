<?php

use App\Http\Controllers\Api\AnalisisController;
use App\Http\Controllers\Api\IkanController;
use App\Http\Controllers\Api\KolamController;
use App\Http\Controllers\Api\LingkunganController;
use App\Http\Controllers\Api\SamplingController;
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

    Route::prefix('kolam/{kolamId}/ikan')
        ->name('api.kolam.ikan.')
        ->controller(IkanController::class)
        ->group(function () {
            Route::get('/', 'show')->whereNumber('kolamId')->name('show');
            Route::post('/gerak', 'gerakIkan')->whereNumber('kolamId')->name('gerak');
            Route::post('/proses-harian', 'prosesHarianIkan')->whereNumber('kolamId')->name('prosesHarian');
        });

    Route::prefix('kolam/{kolamId}')
        ->name('api.kolam.')
        ->controller(SamplingController::class)
        ->group(function () {
            Route::get('/sampel', 'ambilSampel')->whereNumber('kolamId')->name('sampel');
            Route::post('/sampling', 'store')->whereNumber('kolamId')->name('sampling');
        });

    Route::prefix('kolam/{kolamId}')
        ->name('api.kolam.analisis.')
        ->controller(AnalisisController::class)
        ->group(function () {
            Route::get('/alert', 'daftarAlert')->whereNumber('kolamId')->name('alert');
            Route::get('/rekomendasi', 'rekomendasiTerbaru')->whereNumber('kolamId')->name('rekomendasi');
            Route::get('/validasi', 'ringkasanValidasi')->whereNumber('kolamId')->name('validasi');
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
