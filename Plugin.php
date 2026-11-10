<?php

declare(strict_types=1);

namespace App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions;

use App\Plugins\AbstractPlugin;
use App\Plugins\RegisterServerFeature;
use App\Plugins\RegisterServerFeatureAction;
use App\Plugins\RegisterViews;
use App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions\Actions\Check;
use Throwable;

class Plugin extends AbstractPlugin
{
    protected string $name = 'Server Versions';

    protected string $description = 'Shows the exact installed versions of PHP, nginx, Redis, Node and the OS, plus pending security updates';

    /**
     * Vito disables a plugin that throws during boot, so registration failures are reported instead.
     */
    public function boot(): void
    {
        try {
            RegisterViews::make('vito-server-versions')
                ->path(__DIR__.'/views')
                ->register();

            RegisterServerFeature::make('server-versions')
                ->label('Installed versions')
                ->description('Show exact versions of PHP, nginx, Redis, Node and pending updates')
                ->register();

            RegisterServerFeatureAction::make('server-versions', 'check')
                ->label('Check versions')
                ->handler(Check::class)
                ->register();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
