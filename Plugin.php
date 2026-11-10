<?php

declare(strict_types=1);

namespace App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions;

use App\Plugins\AbstractPlugin;
use App\Plugins\RegisterServerFeature;
use App\Plugins\RegisterServerFeatureAction;
use App\Plugins\RegisterViews;
use App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions\Actions\Check;
use App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions\Http\VersionsPage;
use Illuminate\Support\Facades\Route;
use Throwable;

class Plugin extends AbstractPlugin
{
    protected string $name = 'Server Versions';

    protected string $description = 'Shows the exact installed versions of PHP, nginx, Redis, Node and the OS, plus pending security updates';

    /**
     * Vito disables a plugin that throws during boot, so registration failures are reported instead.
     *
     * Registrations live in config, so `php artisan optimize` writes them into the config cache
     * and the next boot starts with them already present. The feature and the action are each
     * registered only when missing, because the core registrars throw "already exists" otherwise.
     * They are checked independently since the cache can hold the feature without the action.
     * The routes are guarded too: they never throw, but `php artisan optimize` caches them as well,
     * and registering them again would list every route twice. RegisterViews needs no guard: it
     * overwrites its config key and never throws.
     */
    public function boot(): void
    {
        try {
            RegisterViews::make('vito-server-versions')
                ->path(__DIR__.'/views')
                ->register();

            $features = config('server.features') ?? [];

            if (! isset($features['server-versions'])) {
                RegisterServerFeature::make('server-versions')
                    ->label('Installed versions')
                    ->description('Show exact versions of PHP, nginx, Redis, Node and pending updates')
                    ->register();
            }

            $actions = config('server.features.server-versions.actions') ?? [];

            if (! isset($actions['check'])) {
                RegisterServerFeatureAction::make('server-versions', 'check')
                    ->label('Check versions')
                    ->handler(Check::class)
                    ->register();
            }

            if (! Route::has('server-versions.index')) {
                Route::middleware(['web', 'auth', 'has-project'])->group(function (): void {
                    Route::get('/installed-versions', [VersionsPage::class, 'index'])
                        ->name('server-versions.index');
                    Route::get('/servers/{server}/installed-versions', [VersionsPage::class, 'show'])
                        ->name('server-versions.show');
                });

                // Names given after a route is added are only indexed on refresh, and Route::has() reads that index.
                Route::getRoutes()->refreshNameLookups();
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
