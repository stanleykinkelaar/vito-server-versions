<?php

declare(strict_types=1);

namespace App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions\Actions;

use App\Exceptions\SSHError;
use App\ServerFeatures\Action;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class Check extends Action
{
    public function name(): string
    {
        return 'Check versions';
    }

    public function active(): bool
    {
        return true;
    }

    public function handle(Request $request): void
    {
        try {
            $output = $this->server->ssh()->exec(
                view('vito-server-versions::check'),
                'server-versions-check',
                timeout: 120,
            );
        } catch (SSHError $e) {
            $request->session()->flash('error', $e->getLog()?->getContent(30) ?? $e->getMessage());

            return;
        } catch (Throwable $e) {
            report($e);
            $request->session()->flash('error', 'Checking versions failed: '.$e->getMessage());

            return;
        }

        $request->session()->flash('success', $this->summary($output));
    }

    private function summary(string $output): string
    {
        $sections = $this->sections($output);
        $parts = [];

        preg_match_all('/^PHP (\d+\.\d+\.\d+)/m', $sections['PHP'] ?? '', $php);
        if ($php[1] !== []) {
            $parts[] = 'PHP '.implode(', ', $php[1]);
        }
        if (preg_match('/nginx\/(\S+)/', $sections['Web'] ?? '', $match) === 1) {
            $parts[] = 'nginx '.$match[1];
        }
        if (preg_match('/v=(\S+)/', $sections['Redis'] ?? '', $match) === 1) {
            $parts[] = 'Redis '.$match[1];
        }
        if (preg_match('/^v\d+\S*/m', $sections['Node'] ?? '', $match) === 1) {
            $parts[] = 'Node '.$match[0];
        }

        $pending = collect(explode("\n", $sections['Pending updates'] ?? ''))
            ->filter(fn (string $line): bool => str_contains($line, '/'));
        $security = $pending->filter(fn (string $line): bool => str_contains($line, '-security'))->count();
        $parts[] = sprintf('%d pending updates (%d security)', $pending->count(), $security);

        return implode(' · ', $parts);
    }

    /**
     * @return array<string, string>
     */
    private function sections(string $output): array
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
}
