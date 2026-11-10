@extends('vito-server-versions::layout', ['title' => 'Installed versions'])

@php
    $isBehind = fn (string $key, ?string $version): bool => $version !== null
        && isset($latest[$key])
        && version_compare(ltrim($version, 'v'), ltrim($latest[$key], 'v'), '<');
@endphp

@section('content')
    <nav class="crumbs">
        <a href="{{ url('/servers') }}">← Servers</a>
    </nav>

    <header>
        <div>
            <h1>Installed versions</h1>
            <div class="muted">
                All servers in {{ $project->name }}. A version marked <span class="badge warning">behind</span> is older than
                the newest one of the same software (or PHP minor version) on another server.
            </div>
        </div>
    </header>

    <div class="card">
        @if ($servers->isEmpty())
            <div class="empty">This project has no servers.</div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Server</th><th>OS</th><th>PHP</th><th>Web</th><th>Redis</th><th>Database</th><th>Node</th>
                        <th>Pending updates</th><th>Checked</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($servers as ['server' => $server, 'report' => $report, 'canCheck' => $canCheck])
                        <tr>
                            <td>
                                <a href="{{ url('/servers/'.$server->id.'/installed-versions') }}">{{ $server->name }}</a>
                                <div class="muted mono">{{ $server->ip }}</div>
                            </td>
                            @if (! $report)
                                <td colspan="7" class="muted">Not checked yet</td>
                                <td>—</td>
                            @elseif (! $report->ok)
                                <td colspan="7"><span class="badge warning">Last check failed</span></td>
                                <td title="{{ $report->checkedAt->toDayDateTimeString() }}">{{ $report->checkedAt->diffForHumans() }}</td>
                            @else
                                <td>{{ $report->os ?? '—' }}</td>
                                <td class="mono">
                                    @forelse ($report->php as $php)
                                        <div>
                                            {{ $php['version'] ?? $php['minor'] }}
                                            @if ($isBehind('php:'.$php['minor'], $php['version']))
                                                <span class="badge warning">behind</span>
                                            @endif
                                        </div>
                                    @empty
                                        —
                                    @endforelse
                                </td>
                                @foreach ([['nginx', 'Caddy'], ['Redis'], ['MySQL', 'MariaDB', 'PostgreSQL'], ['Node']] as $names)
                                    <td class="mono">
                                        @php($found = false)
                                        @foreach ($names as $name)
                                            @if ($version = $report->version($name))
                                                @php($found = true)
                                                <div>
                                                    @if (count($names) > 1)<span class="muted">{{ $name }}</span>@endif
                                                    {{ $version }}
                                                    @if ($isBehind($name, $version))
                                                        <span class="badge warning">behind</span>
                                                    @endif
                                                </div>
                                            @endif
                                        @endforeach
                                        @unless ($found) — @endunless
                                    </td>
                                @endforeach
                                <td>
                                    @if ($report->securityCount() > 0)
                                        <span class="badge danger">{{ $report->securityCount() }} security</span>
                                    @endif
                                    @if (count($report->pending) > 0)
                                        <span class="badge">{{ count($report->pending) }} total</span>
                                    @else
                                        <span class="badge ok">none</span>
                                    @endif
                                </td>
                                <td title="{{ $report->checkedAt->toDayDateTimeString() }}">{{ $report->checkedAt->diffForHumans() }}</td>
                            @endif
                            <td>
                                @if ($canCheck)
                                    @include('vito-server-versions::partials.check-button', ['class' => 'small', 'label' => 'Check'])
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <p class="muted" style="font-size: 13px">
        Open a server for exact package versions. Each check connects over SSH and takes a few seconds per server.
    </p>
@endsection
