<?php

use App\Models\User;

dataset('aqua pages', [
    'dashboard' => ['dashboard'],
    'catat timbang' => ['catat'],
    'kondisi air' => ['air'],
    'hasil panen' => ['hasil'],
    'jadwal panen' => ['panen'],
    'daftar kolam' => ['kolam'],
    'pengaturan' => ['pengaturan'],
]);

test('guests are redirected to the login page', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with('aqua pages');

test('authenticated users can visit every aqua page', function (string $routeName) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk();
})->with('aqua pages');
