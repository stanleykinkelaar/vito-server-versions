<?php

use App\Facades\SSH;
use App\Plugins\RegisterViews;
use App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new Plugin)->boot();

    foreach (RegisterViews::get() as $name => $path) {
        View::addNamespace($name, $path);
    }
});

test('check versions stores a server log and flashes a summary', function () {
    SSH::fake(file_get_contents(__DIR__.'/fixtures/check-output.txt'));

    $this->actingAs($this->user)
        ->post(route('server-features.action', [
            'server' => $this->server,
            'feature' => 'server-versions',
            'action' => 'check',
        ]))
        ->assertRedirect()
        ->assertSessionHas('success', 'PHP 8.4.27, 8.3.14 · nginx 1.24.0 · Redis 7.0.15 · Node v22.11.0 · 2 pending updates (1 security)');

    SSH::assertExecutedContains('apt list --upgradable');

    $this->assertDatabaseHas('server_logs', [
        'server_id' => $this->server->id,
        'type' => 'server-versions-check',
    ]);
});

test('check versions flashes an error when ssh fails', function () {
    SSH::fake()->execWillFail();

    $this->actingAs($this->user)
        ->post(route('server-features.action', [
            'server' => $this->server,
            'feature' => 'server-versions',
            'action' => 'check',
        ]))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('server_logs', [
        'server_id' => $this->server->id,
        'type' => 'server-versions-check',
    ]);
});
