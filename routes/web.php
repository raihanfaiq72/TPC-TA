<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Halaman aplikasi SmartAqua (tampilan frontend).
    Route::inertia('kolam', 'Kolam')->name('kolam');
    Route::inertia('kolam/{id}', 'KolamDetail')->whereNumber('id')->name('kolam.detail');
    Route::inertia('pengaturan', 'Pengaturan')->name('pengaturan');
});

require __DIR__.'/settings.php';
