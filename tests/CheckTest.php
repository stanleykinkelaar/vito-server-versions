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
    SSH::fake(<<<'OUTPUT'
== OS
Ubuntu 24.04.3 LTS
== Kernel
6.8.0-87-generic
== PHP
PHP 8.4.27 (cli) (built: Oct 24 2026 10:01:12) (NTS)
  php8.4-fpm 8.4.27-1+ubuntu24.04.1+deb.sury.org+1
  php8.4-cli 8.4.27-1+ubuntu24.04.1+deb.sury.org+1
  php8.4-fpm: active
PHP 8.3.14 (cli) (built: Nov 25 2025 18:23:44) (NTS)
  php8.3-fpm 8.3.14-1+ubuntu24.04.1+deb.sury.org+1
  php8.3-fpm: inactive
== Web
nginx version: nginx/1.24.0 (Ubuntu)
== Redis
Redis server v=7.0.15 sha=00000000:0 malloc=jemalloc-5.3.0 bits=64 build=62c7a5d52c72f4cd
== Database
mysql  Ver 8.0.43-0ubuntu0.24.04.1 for Linux on x86_64 ((Ubuntu))
== Node
v22.11.0
== Composer
Composer version 2.8.12 2025-09-19 13:41:59
== Pending updates (php/nginx/redis/mysql/openssl)
libssl3t64/noble-updates,noble-security 3.0.13-0ubuntu3.6 amd64 [upgradable from: 3.0.13-0ubuntu3.5]
php8.4-fpm/noble 8.4.28-1+ubuntu24.04.1+deb.sury.org+1 amd64 [upgradable from: 8.4.27-1+ubuntu24.04.1+deb.sury.org+1]
== Unattended upgrades
enabled
== Last apt update
2026-11-10 06:12:01.123456789 +0000
OUTPUT);

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
