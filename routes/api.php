<?php

use App\Http\Controllers\Api\AnalisisController;
use App\Http\Controllers\Api\DatasetController;
use App\Http\Controllers\Api\IkanController;
use App\Http\Controllers\Api\KolamController;
use App\Http\Controllers\Api\LingkunganController;
use App\Http\Controllers\Api\PakanController;
use App\Http\Controllers\Api\SamplingController;
use App\Http\Controllers\Api\SensorController;
use App\Http\Controllers\Api\SimulasiController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json([
    'status' => 'ok',
    'app' => config('app.name'),
]));

Route::middleware('throttle:600,1')->group(function () {

    Route::prefix('kolam')
        ->name('api.kolam.')
        ->controller(KolamController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{id}', 'show')->whereNumber('id')->name('show');
            Route::put('/{id}', 'update')->whereNumber('id')->name('update');
            Route::post('/{id}/reset', 'reset')->whereNumber('id')->name('reset');
            Route::post('/{id}/foto', 'uploadFoto')->whereNumber('id')->name('foto');
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
            Route::get('/riwayat', 'riwayat')->whereNumber('kolamId')->name('riwayat');
            Route::post('/gerak', 'gerakIkan')->whereNumber('kolamId')->name('gerak');
            Route::post('/proses-harian', 'prosesHarianIkan')->whereNumber('kolamId')->name('prosesHarian');
        });

    Route::prefix('kolam/{kolamId}')
        ->name('api.kolam.')
        ->controller(SamplingController::class)
        ->group(function () {
            Route::get('/sampel', 'ambilSampel')->whereNumber('kolamId')->name('sampel');
            Route::get('/sampling', 'index')->whereNumber('kolamId')->name('sampling.index');
            Route::post('/sampling', 'store')->whereNumber('kolamId')->name('sampling');
            Route::get('/sampling/{samplingId}', 'show')->whereNumber(['kolamId', 'samplingId'])->name('sampling.show');
            Route::put('/sampling/{samplingId}', 'update')->whereNumber(['kolamId', 'samplingId'])->name('sampling.update');
            Route::patch('/sampling/{samplingId}', 'update')->whereNumber(['kolamId', 'samplingId'])->name('sampling.update.patch');
            Route::delete('/sampling/{samplingId}', 'destroy')->whereNumber(['kolamId', 'samplingId'])->name('sampling.destroy');
        });

    Route::put('/kolam/{kolamId}/pakan/jadwal', [PakanController::class, 'aturJadwal'])
        ->whereNumber('kolamId')
        ->name('api.kolam.pakan.jadwal');

    Route::prefix('kolam/{kolamId}/sensor')
        ->name('api.kolam.sensor.')
        ->controller(SensorController::class)
        ->group(function () {
            Route::post('/baca', 'bacaSemuaSensor')->whereNumber('kolamId')->name('baca');
            Route::get('/grafik', 'grafik')->whereNumber('kolamId')->name('grafik');
            Route::get('/terbaru', 'terbaru')->whereNumber('kolamId')->name('terbaru');
            Route::get('/riwayat', 'riwayat')->whereNumber('kolamId')->name('riwayat');
            Route::patch('/{perangkatId}', 'updateStatus')
                ->whereNumber(['kolamId', 'perangkatId'])
                ->name('status');
        });

    Route::prefix('kolam/{id}/simulasi')
        ->name('api.kolam.simulasi.')
        ->controller(SimulasiController::class)
        ->group(function () {
            Route::get('/', 'status')->whereNumber('id')->name('status');
            Route::post('/tick', 'tick')->whereNumber('id')->name('tick');
            Route::post('/auto-pilot/start', 'startAutoPilot')->whereNumber('id')->name('auto-pilot.start');
            Route::post('/auto-pilot/stop', 'stopAutoPilot')->whereNumber('id')->name('auto-pilot.stop');
        });

    Route::prefix('kolam/{kolamId}')
        ->name('api.kolam.analisis.')
        ->controller(AnalisisController::class)
        ->group(function () {
            Route::get('/alert', 'daftarAlert')->whereNumber('kolamId')->name('alert');
            Route::get('/rekomendasi', 'rekomendasiTerbaru')->whereNumber('kolamId')->name('rekomendasi');
            Route::get('/validasi', 'ringkasanValidasi')->whereNumber('kolamId')->name('validasi');
        });

    Route::prefix('kolam/{kolamId}/export')
        ->name('api.kolam.export.')
        ->controller(DatasetController::class)
        ->group(function () {
            Route::get('/ringkas', 'ringkas')->whereNumber('kolamId')->name('ringkas');
            Route::get('/lengkap', 'lengkap')->whereNumber('kolamId')->name('lengkap');
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
