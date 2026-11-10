<?php

declare(strict_types=1);

namespace App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions\Actions;

use App\DTOs\DynamicField;
use App\DTOs\DynamicForm;
use App\Exceptions\SSHError;
use App\ServerFeatures\Action;
use App\Vito\Plugins\Stanleykinkelaar\VitoServerVersions\Support\Report;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
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

    /**
     * Shown at the top of the action dialog, so the last result is visible before running a new check.
     * Vito calls this whenever it serializes a server, so it uses plain URLs: route() would throw on
     * every server page if the plugin routes were ever missing.
     */
    public function form(): ?DynamicForm
    {
        try {
            $report = Report::latestFor($this->server);
        } catch (Throwable $e) {
            // Losing the summary is better than breaking every page that lists this server.
            report($e);

            return null;
        }

        $last = DynamicField::make('last_check')
            ->alert()
            ->link('Open installed versions', url('/servers/'.$this->server->id.'/installed-versions'));

        if ($report === null) {
            $last->label('Not checked yet')->description('Run a check to see the installed versions. ');
        } elseif (! $report->ok) {
            $last->label('Last check failed '.$report->checkedAt->diffForHumans())
                ->description('Run the check again or open the log for details. ')
                ->options(['type' => 'warning']);
        } else {
            $last->label('Last checked '.$report->checkedAt->diffForHumans())
                ->description($report->summary().'. ');
            if ($report->securityCount() > 0) {
                $last->options(['type' => 'warning']);
            }
        }

        return DynamicForm::make([
            $last,
            DynamicField::make('all_servers')
                ->alert()
                ->description('Compare versions across servers: ')
                ->link('All servers', url('/installed-versions')),
        ]);
    }

    public function handle(Request $request): void
    {
        try {
            $output = $this->server->ssh()->exec(
                view('vito-server-versions::check'),
                Report::LOG_TYPE,
                timeout: 120,
            );
        } catch (SSHError $e) {
            $request->session()->flash('error', $e->getLog()?->getContent(30) ?? $e->getMessage());

            return;
        } catch (Throwable $e) {
            report($e);
            $request->session()->flash('error', 'Checking versions failed: '.$e->getMessage());

            return;
        } finally {
            Report::forget($this->server);
        }

        $request->session()->flash('success', Report::parse($output, 0, CarbonImmutable::now())->summary());
    }
}
