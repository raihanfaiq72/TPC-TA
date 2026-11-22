<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Halaman aplikasi SmartAqua (tampilan frontend).
    Route::inertia('catat', 'Catat')->name('catat');
    Route::inertia('air', 'Air')->name('air');
    Route::inertia('hasil', 'Hasil')->name('hasil');
    Route::inertia('panen', 'Panen')->name('panen');
    Route::inertia('kolam', 'Kolam')->name('kolam');
    Route::inertia('pengaturan', 'Pengaturan')->name('pengaturan');
});

require __DIR__.'/settings.php';
