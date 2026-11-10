<?php

declare(strict_types=1);

namespace App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions\Support;

use App\Models\Server;
use App\Models\ServerLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The parsed output of one version check.
 *
 * The latest report per server is cached because Vito builds every server's feature list,
 * and with it Check::form(), whenever a server is serialized.
 */
final readonly class Report
{
    public const string LOG_TYPE = 'server-versions-check';

    private const int CACHE_SECONDS = 86400;

    /**
     * @param  array<int, array{minor: string, version: ?string, cli: ?string, fpm: ?string, fpm_status: ?string}>  $php
     * @param  array<int, array{name: string, version: string}>  $software
     * @param  array<int, array{package: string, source: string, available: string, installed: ?string, security: bool}>  $pending
     */
    public function __construct(
        public int $logId,
        public CarbonImmutable $checkedAt,
        public bool $ok,
        public ?string $os = null,
        public ?string $kernel = null,
        public array $php = [],
        public array $software = [],
        public array $pending = [],
        public ?string $unattendedUpgrades = null,
        public ?string $lastAptUpdate = null,
    ) {}

    public static function latestFor(Server $server): ?self
    {
        $cached = Cache::remember(self::cacheKey($server), self::CACHE_SECONDS, function () use ($server): array {
            $log = ServerLog::query()
                ->where('server_id', $server->id)
                ->where('type', self::LOG_TYPE)
                ->latest('id')
                ->first();

            return $log ? self::fromLog($log)->toArray() : [];
        });

        return $cached === [] ? null : self::fromArray($cached);
    }

    public static function forget(Server $server): void
    {
        Cache::forget(self::cacheKey($server));
    }

    public static function fromLog(ServerLog $log): self
    {
        return self::parse(
            $log->getContent() ?? '',
            $log->id,
            CarbonImmutable::parse($log->created_at),
        );
    }

    public static function parse(string $output, int $logId, CarbonImmutable $checkedAt): self
    {
        $sections = self::sections($output);

        return new self(
            logId: $logId,
            checkedAt: $checkedAt,
            ok: isset($sections['OS'], $sections['Pending updates']),
            os: self::firstLine($sections['OS'] ?? ''),
            kernel: self::firstLine($sections['Kernel'] ?? ''),
            php: self::php($sections['PHP'] ?? ''),
            software: self::software($sections),
            pending: self::pending($sections['Pending updates'] ?? ''),
            unattendedUpgrades: self::firstLine($sections['Unattended upgrades'] ?? ''),
            lastAptUpdate: self::aptStamp($sections['Last apt update'] ?? ''),
        );
    }

    public function securityCount(): int
    {
        return count(array_filter($this->pending, fn (array $update): bool => $update['security']));
    }

    public function version(string $name): ?string
    {
        foreach ($this->software as $item) {
            if ($item['name'] === $name) {
                return $item['version'];
            }
        }

        return null;
    }

    public function summary(): string
    {
        $parts = [];

        $php = array_filter(array_column($this->php, 'version'));
        if ($php !== []) {
            $parts[] = 'PHP '.implode(', ', $php);
        }
        foreach (['nginx', 'Redis', 'Node'] as $name) {
            if ($version = $this->version($name)) {
                $parts[] = $name.' '.$version;
            }
        }
        $parts[] = sprintf('%d pending updates (%d security)', count($this->pending), $this->securityCount());

        return implode(' · ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'log_id' => $this->logId,
            'checked_at' => $this->checkedAt->toIso8601String(),
            'ok' => $this->ok,
            'os' => $this->os,
            'kernel' => $this->kernel,
            'php' => $this->php,
            'software' => $this->software,
            'pending' => $this->pending,
            'unattended_upgrades' => $this->unattendedUpgrades,
            'last_apt_update' => $this->lastAptUpdate,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            logId: $data['log_id'],
            checkedAt: CarbonImmutable::parse($data['checked_at']),
            ok: $data['ok'],
            os: $data['os'],
            kernel: $data['kernel'],
            php: $data['php'],
            software: $data['software'],
            pending: $data['pending'],
            unattendedUpgrades: $data['unattended_upgrades'],
            lastAptUpdate: $data['last_apt_update'],
        );
    }

    private static function cacheKey(Server $server): string
    {
        return 'server-versions:report:'.$server->id;
    }

    /**
     * @return array<string, string>
     */
    private static function sections(string $output): array
    {
        $sections = [];
        $current = null;

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (str_starts_with($line, '== ')) {
                $current = Str::before(substr($line, 3), ' (');
                $sections[$current] = '';

                continue;
            }
            if ($current !== null) {
                $sections[$current] .= $line."\n";
            }
        }

        return $sections;
    }

    private static function firstLine(string $section): ?string
    {
        foreach (explode("\n", $section) as $line) {
            if (trim($line) !== '') {
                return trim($line);
            }
        }

        return null;
    }

    /**
     * @return array<int, array{minor: string, version: ?string, cli: ?string, fpm: ?string, fpm_status: ?string}>
     */
    private static function php(string $section): array
    {
        $php = [];

        foreach (explode("\n", $section) as $line) {
            if (preg_match('/^PHP ((\d+\.\d+)\.\S+)/', $line, $match) === 1) {
                [$minor, $field] = [$match[2], ['version' => $match[1]]];
            } elseif (preg_match('/^\s+php(\d+\.\d+)-fpm: (\S+)/', $line, $match) === 1) {
                [$minor, $field] = [$match[1], ['fpm_status' => $match[2]]];
            } elseif (preg_match('/^\s+php(\d+\.\d+)-(cli|fpm) (\S+)/', $line, $match) === 1) {
                [$minor, $field] = [$match[1], [$match[2] => $match[3]]];
            } else {
                continue;
            }

            $php[$minor] = [
                ...$php[$minor] ?? ['minor' => $minor, 'version' => null, 'cli' => null, 'fpm' => null, 'fpm_status' => null],
                ...$field,
            ];
        }

        return array_values($php);
    }

    /**
     * @param  array<string, string>  $sections
     * @return array<int, array{name: string, version: string}>
     */
    private static function software(array $sections): array
    {
        $patterns = [
            ['nginx', 'Web', '/nginx\/(\S+)/'],
            ['Caddy', 'Web', '/^(v\d+\.\d+\.\d+\S*)/m'],
            ['Redis', 'Redis', '/v=(\S+)/'],
            ['MariaDB', 'Database', '/(\d+\.\d+\.\d+)-MariaDB/'],
            ['MySQL', 'Database', '/^mysql\s+Ver (\d+\.\d+\.\d+(?!-MariaDB)\S*) for/m'],
            ['PostgreSQL', 'Database', '/psql \(PostgreSQL\) (\S+)/'],
            ['Node', 'Node', '/^(v\d+\S*)/m'],
            ['Composer', 'Composer', '/Composer version (\S+)/'],
        ];

        $software = [];
        foreach ($patterns as [$name, $section, $pattern]) {
            if (preg_match($pattern, $sections[$section] ?? '', $match) === 1) {
                $software[] = ['name' => $name, 'version' => $match[1]];
            }
        }

        return $software;
    }

    /**
     * @return array<int, array{package: string, source: string, available: string, installed: ?string, security: bool}>
     */
    private static function pending(string $section): array
    {
        $pending = [];

        foreach (explode("\n", $section) as $line) {
            if (preg_match('/^(\S+?)\/(\S+) (\S+) \S+(?: \[upgradable from: ([^\]]+)\])?/', $line, $match) !== 1) {
                continue;
            }
            $pending[] = [
                'package' => $match[1],
                'source' => $match[2],
                'available' => $match[3],
                'installed' => $match[4] ?? null,
                'security' => str_contains($match[2], '-security'),
            ];
        }

        usort($pending, fn (array $a, array $b): int => [$b['security'], $a['package']] <=> [$a['security'], $b['package']]);

        return $pending;
    }

    private static function aptStamp(string $section): ?string
    {
        $line = self::firstLine($section);

        if ($line !== null && preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\S* (\S+)$/', $line, $match) === 1) {
            return $match[1].' '.$match[2];
        }

        return $line;
    }
}
