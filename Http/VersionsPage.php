<?php

declare(strict_types=1);

namespace App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions\Http;

use App\Models\Server;
use App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions\Support\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Not named or built as a controller: Vito's architecture tests reserve both for App\Http\Controllers.
 */
class VersionsPage
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $servers = $user->currentProject->servers()
            ->orderBy('name')
            ->get()
            ->filter(fn (Server $server): bool => $user->can('view', $server))
            ->map(fn (Server $server): array => [
                'server' => $server,
                'report' => Report::latestFor($server),
                'canCheck' => $user->can('update', $server),
            ]);

        return view('vito-server-versions::index', [
            'project' => $user->currentProject,
            'servers' => $servers,
            'latest' => $this->latest($servers->pluck('report')->filter()->all()),
        ]);
    }

    /**
     * The newest version of each software, and of each PHP minor version, across the given reports.
     *
     * @param  array<int, Report>  $reports
     * @return array<string, string>
     */
    private function latest(array $reports): array
    {
        $latest = [];
        $keep = function (string $key, ?string $version) use (&$latest): void {
            if ($version !== null && (! isset($latest[$key]) || version_compare(ltrim($version, 'v'), ltrim($latest[$key], 'v'), '>'))) {
                $latest[$key] = $version;
            }
        };

        foreach ($reports as $report) {
            foreach ($report->php as $php) {
                $keep('php:'.$php['minor'], $php['version']);
            }
            foreach ($report->software as $item) {
                $keep($item['name'], $item['version']);
            }
        }

        return $latest;
    }

    public function show(Request $request, Server $server): View
    {
        Gate::authorize('view', $server);

        return view('vito-server-versions::show', [
            'server' => $server,
            'report' => Report::latestFor($server),
            'canCheck' => $request->user()->can('update', $server),
        ]);
    }
}
