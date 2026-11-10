<?php

use App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions\Actions\Check;
use App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Exceptions;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $features = config('server.features') ?? [];
    Arr::forget($features, 'server-versions');
    config(['server.features' => $features]);

    Exceptions::fake();
});

test('booting twice registers the feature and action once without reporting', function () {
    (new Plugin)->boot();
    (new Plugin)->boot();

    Exceptions::assertNothingReported();

    $features = config('server.features');

    expect($features)->toHaveKey('server-versions')
        ->and($features['server-versions']['label'])->toBe('Installed versions')
        ->and(array_keys($features['server-versions']['actions']))->toBe(['check'])
        ->and($features['server-versions']['actions']['check']['handler'])->toBe(Check::class);
});

test('boot adds the action when the cached config has the feature without actions', function () {
    config(['server.features.server-versions' => [
        'label' => 'Installed versions',
        'description' => 'Show exact versions of PHP, nginx, Redis, Node and pending updates',
    ]]);

    (new Plugin)->boot();

    Exceptions::assertNothingReported();

    expect(array_keys(config('server.features.server-versions.actions')))->toBe(['check'])
        ->and(config('server.features.server-versions.actions.check.handler'))->toBe(Check::class);
});

test('boot changes nothing when the cached config has the feature and action', function () {
    config(['server.features.server-versions' => [
        'label' => 'Installed versions',
        'description' => 'Show exact versions of PHP, nginx, Redis, Node and pending updates',
        'actions' => [
            'check' => [
                'label' => 'Check versions',
                'handler' => Check::class,
                'form' => [],
            ],
        ],
    ]]);

    $before = config('server.features');

    (new Plugin)->boot();

    Exceptions::assertNothingReported();

    expect(config('server.features'))->toBe($before);
});
