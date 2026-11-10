<?php

use App\Facades\SSH;
use App\Models\Server;
use App\Models\User;
use App\Plugins\RegisterViews;
use App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions\Actions\Check;
use App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions\Plugin;
use App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions\Support\Report;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Log files are named by server id and second, so tests would otherwise read each other's logs.
    Storage::fake(config('core.logs_disk'));

    (new Plugin)->boot();

    foreach (RegisterViews::get() as $name => $path) {
        View::addNamespace($name, $path);
    }

    $this->output = file_get_contents(__DIR__.'/fixtures/check-output.txt');
});

function runCheck(TestCase $test, Server $server, string $output): void
{
    SSH::fake($output);

    $test->actingAs($test->user)
        ->post(route('server-features.action', ['server' => $server, 'feature' => 'server-versions', 'action' => 'check']))
        ->assertSessionHas('success');
}

test('parses exact versions and lists security updates first', function () {
    $report = Report::parse($this->output, 1, CarbonImmutable::now());

    expect($report->ok)->toBeTrue()
        ->and($report->os)->toBe('Ubuntu 24.04.3 LTS')
        ->and($report->kernel)->toBe('6.8.0-87-generic')
        ->and($report->php)->toBe([
            ['minor' => '8.4', 'version' => '8.4.27', 'cli' => '8.4.27-1+ubuntu24.04.1+deb.sury.org+1', 'fpm' => '8.4.27-1+ubuntu24.04.1+deb.sury.org+1', 'fpm_status' => 'active'],
            ['minor' => '8.3', 'version' => '8.3.14', 'cli' => null, 'fpm' => '8.3.14-1+ubuntu24.04.1+deb.sury.org+1', 'fpm_status' => 'inactive'],
        ])
        ->and($report->software)->toBe([
            ['name' => 'nginx', 'version' => '1.24.0'],
            ['name' => 'Redis', 'version' => '7.0.15'],
            ['name' => 'MySQL', 'version' => '8.0.43-0ubuntu0.24.04.1'],
            ['name' => 'Node', 'version' => 'v22.11.0'],
            ['name' => 'Composer', 'version' => '2.8.12'],
        ])
        ->and(array_column($report->pending, 'package'))->toBe(['libssl3t64', 'php8.4-fpm'])
        ->and($report->pending[0])->toMatchArray(['installed' => '3.0.13-0ubuntu3.5', 'available' => '3.0.13-0ubuntu3.6', 'security' => true])
        ->and($report->securityCount())->toBe(1)
        ->and($report->unattendedUpgrades)->toBe('enabled')
        ->and($report->lastAptUpdate)->toBe('2026-11-10 06:12:01 +0000');
});

test('parses MariaDB, PostgreSQL and Caddy', function () {
    $report = Report::parse(<<<'OUTPUT'
== OS
Debian GNU/Linux 12 (bookworm)
== Web
v2.7.6 h1:w0NymbG2m9PcvKWsrXO6EEkY9Ra7FJMYBAU8Lyl6hzwo=
== Database
mysql  Ver 15.1 Distrib 10.11.6-MariaDB, for debian-linux-gnu (x86_64) using  EditLine wrapper
psql (PostgreSQL) 16.10 (Ubuntu 16.10-0ubuntu0.24.04.1)
== Pending updates (php/nginx/redis/mysql/openssl)
none
OUTPUT, 1, CarbonImmutable::now());

    expect($report->software)->toBe([
        ['name' => 'Caddy', 'version' => 'v2.7.6'],
        ['name' => 'MariaDB', 'version' => '10.11.6'],
        ['name' => 'PostgreSQL', 'version' => '16.10'],
    ])->and($report->pending)->toBe([]);
});

test('the server page shows the exact versions of the latest check', function () {
    $this->actingAs($this->user)
        ->get(route('server-versions.show', $this->server))
        ->assertOk()
        ->assertSee('not been checked yet');

    runCheck($this, $this->server, $this->output);

    $this->actingAs($this->user)
        ->get(route('server-versions.show', $this->server))
        ->assertOk()
        ->assertSee('8.4.27-1+ubuntu24.04.1+deb.sury.org+1')
        ->assertSee('8.0.43-0ubuntu0.24.04.1')
        ->assertSee('1 security update pending')
        ->assertSee(url('/servers/'.$this->server->id.'/features/server-versions/check'));
});

test('a new check replaces the cached report', function () {
    runCheck($this, $this->server, $this->output);
    expect(Report::latestFor($this->server)->version('nginx'))->toBe('1.24.0');

    runCheck($this, $this->server, str_replace('nginx/1.24.0', 'nginx/1.26.3', $this->output));
    expect(Report::latestFor($this->server)->version('nginx'))->toBe('1.26.3');
});

test('a failed check is shown as failed', function () {
    SSH::fake()->execWillFail();

    $this->actingAs($this->user)
        ->post(route('server-features.action', ['server' => $this->server, 'feature' => 'server-versions', 'action' => 'check']))
        ->assertSessionHas('error');

    expect(Report::latestFor($this->server)->ok)->toBeFalse();

    $this->actingAs($this->user)
        ->get(route('server-versions.show', $this->server))
        ->assertOk()
        ->assertSee('The last check failed');
});

test('the overview lists every server and marks versions that are behind', function () {
    $other = Server::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $this->user->current_project_id,
        'name' => 'other-server',
    ]);

    runCheck($this, $this->server, $this->output);
    runCheck($this, $other, str_replace(['PHP 8.4.27', 'nginx/1.24.0'], ['PHP 8.4.28', 'nginx/1.26.3'], $this->output));

    $response = $this->actingAs($this->user)
        ->get(route('server-versions.index'))
        ->assertOk()
        ->assertSee($this->server->name)
        ->assertSee('other-server')
        ->assertSee('1 security');

    $rows = Str::after($response->getContent(), '<tbody>');

    expect(substr_count($rows, '<span class="badge warning">behind</span>'))->toBe(2);
});

test('the overview shows servers that were never checked', function () {
    $this->actingAs($this->user)
        ->get(route('server-versions.index'))
        ->assertOk()
        ->assertSee('Not checked yet');
});

test('users without access to the server cannot open its page', function () {
    $stranger = User::factory()->create();
    $stranger->ensureHasDefaultProject();

    $this->actingAs($stranger)
        ->get(route('server-versions.show', $this->server))
        ->assertForbidden();

    $this->actingAs($stranger)
        ->get(route('server-versions.index'))
        ->assertOk()
        ->assertDontSee($this->server->name);
});

test('guests are redirected to login', function () {
    $this->get(route('server-versions.index'))->assertRedirect(url('/'));
});

test('the check dialog shows the last result and links to the pages', function () {
    $form = fn () => collect($this->server->fresh()->features()['server-versions']['actions']['check']['form'])->keyBy('name');

    expect($form()['last_check']['label'])->toBe('Not checked yet')
        ->and($form()['last_check']['link']['url'])->toBe(url('/servers/'.$this->server->id.'/installed-versions'))
        ->and($form()['all_servers']['link']['url'])->toBe(url('/installed-versions'));

    runCheck($this, $this->server, $this->output);

    expect($form()['last_check']['label'])->toStartWith('Last checked')
        ->and($form()['last_check']['description'])->toContain('PHP 8.4.27, 8.3.14 · nginx 1.24.0')
        ->and($form()['last_check']['options'])->toBe(['type' => 'warning']);
});

test('the check dialog falls back to no form when the last result cannot be read', function () {
    Exceptions::fake();
    Cache::shouldReceive('remember')->andThrow(new RuntimeException('cache down'));

    expect((new Check($this->server))->form())->toBeNull();

    Exceptions::assertReported(RuntimeException::class);
});
