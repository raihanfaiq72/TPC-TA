<?php

use App\Models\User;

dataset('aqua pages', [
    'dashboard' => ['dashboard'],
    'daftar kolam' => ['kolam'],
    'pengaturan' => ['pengaturan'],
]);

test('guests are redirected to the login page', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with('aqua pages');

test('removed aqua pages are not available', function (string $path) {
    $this->get($path)->assertNotFound();
})->with(['/catat', '/air', '/hasil', '/panen']);

test('authenticated users can visit every aqua page', function (string $routeName) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk();
})->with('aqua pages');
