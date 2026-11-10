@extends('vito-server-versions::layout', ['title' => 'Installed versions · '.$server->name])

@section('content')
    <nav class="crumbs">
        <a href="{{ url('/servers/'.$server->id) }}">← {{ $server->name }}</a>
        <span>·</span>
        <a href="{{ url('/installed-versions') }}">All servers</a>
    </nav>

    <header>
        <div>
            <h1>Installed versions</h1>
            <div class="muted">
                {{ $server->name }} · {{ $server->ip }}
                @if ($report)
                    · checked <span title="{{ $report->checkedAt->toDayDateTimeString() }}">{{ $report->checkedAt->diffForHumans() }}</span>
                @endif
            </div>
        </div>
        <div class="actions">
            @if ($report)
                <a class="button" href="{{ url('/servers/'.$server->id.'/logs/'.$report->logId) }}" target="_blank">Raw output</a>
            @endif
            @if ($canCheck)
                @include('vito-server-versions::partials.check-button')
            @endif
        </div>
    </header>

    @if (! $report)
        <div class="card"><div class="empty">This server has not been checked yet.@if ($canCheck) Click <strong>Check now</strong>.@endif</div></div>
    @elseif (! $report->ok)
        <div class="notice warning">
            The last check failed. Open the raw output for details, or run the check again.
        </div>
    @else
        @if ($report->securityCount() > 0)
            <div class="notice warning">
                {{ $report->securityCount() }} security {{ \Illuminate\Support\Str::plural('update', $report->securityCount()) }} pending.
            </div>
        @endif

        <div class="card">
            <h2>System</h2>
            <dl class="grid" style="margin: 0">
                <div><dt>OS</dt><dd>{{ $report->os ?? '—' }}</dd></div>
                <div><dt>Kernel</dt><dd class="mono">{{ $report->kernel ?? '—' }}</dd></div>
                <div>
                    <dt>Unattended upgrades</dt>
                    <dd>
                        @if ($report->unattendedUpgrades === 'enabled')
                            <span class="badge ok">enabled</span>
                        @else
                            <span class="badge warning">{{ $report->unattendedUpgrades ?? 'unknown' }}</span>
                        @endif
                    </dd>
                </div>
                <div><dt>Last apt update</dt><dd class="mono">{{ $report->lastAptUpdate ?? 'unknown' }}</dd></div>
            </dl>
        </div>

        <div class="card">
            <h2>PHP</h2>
            @if ($report->php === [])
                <div class="empty">No PHP versions found.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Version</th><th>CLI package</th><th>FPM package</th><th>FPM</th></tr></thead>
                        <tbody>
                        @foreach ($report->php as $php)
                            <tr>
                                <td class="mono">{{ $php['version'] ?? $php['minor'] }}</td>
                                <td class="mono">{{ $php['cli'] ?? '—' }}</td>
                                <td class="mono">{{ $php['fpm'] ?? '—' }}</td>
                                <td>
                                    @if ($php['fpm_status'] === 'active')
                                        <span class="badge ok">active</span>
                                    @elseif ($php['fpm_status'])
                                        <span class="badge">{{ $php['fpm_status'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card">
            <h2>Software</h2>
            @if ($report->software === [])
                <div class="empty">None of the tracked software was found.</div>
            @else
                <table>
                    <thead><tr><th>Name</th><th>Version</th></tr></thead>
                    <tbody>
                    @foreach ($report->software as $item)
                        <tr><td>{{ $item['name'] }}</td><td class="mono">{{ $item['version'] }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="card">
            <h2>Pending updates <span class="muted" style="font-weight: 400">php, nginx, redis, mysql, mariadb, openssl</span></h2>
            @if ($report->pending === [])
                <div class="empty">No pending updates.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Package</th><th>Installed</th><th>Available</th><th>Source</th></tr></thead>
                        <tbody>
                        @foreach ($report->pending as $update)
                            <tr>
                                <td>
                                    <span class="mono">{{ $update['package'] }}</span>
                                    @if ($update['security'])
                                        <span class="badge danger">security</span>
                                    @endif
                                </td>
                                <td class="mono">{{ $update['installed'] ?? '—' }}</td>
                                <td class="mono">{{ $update['available'] }}</td>
                                <td class="mono muted">{{ $update['source'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <p class="muted" style="font-size: 13px">
            Distribution packages often backport security fixes without changing the upstream version. When checking a CVE,
            compare against the package version, not only the version PHP or nginx reports.
        </p>
    @endif
@endsection
