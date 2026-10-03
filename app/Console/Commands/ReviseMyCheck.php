<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;

/**
 * What this install still needs before every part of ReviseMy works for real.
 *
 * Capture, vision, realtime, Connect and billing each switch on from a
 * setting on a host's page, and each fails out of sight when it's missing.
 * This reads the install and says each thing as ready or not, with the one
 * thing to do. It exits 1 while anything required is missing; optional parts
 * are listed as notes. Ported from Koati's koati:check.
 *
 * Reads only. On Laravel Cloud: `cloud command:run "php artisan revisemy:check"`.
 */
class ReviseMyCheck extends Command
{
    protected $signature = 'revisemy:check';

    protected $description = 'Say what this install still needs before reviews, Connect and webhooks work for real';

    /** Stamped each minute by the scheduler (routes/console.php), so a scheduler that isn't running shows. */
    public const HEARTBEAT = 'revisemy:scheduler-heartbeat';

    private bool $missing = false;

    public function handle(): int
    {
        $this->section('The app');
        $this->appUrl();
        $this->check(config('database.default') !== 'sqlite' || app()->environment('local'), 'Reviews are kept in a real database.', 'DB_CONNECTION is sqlite, which a deploy on Laravel Cloud throws away. Attach Postgres.');

        $this->section('Screenshots');
        $disk = (string) config('filesystems.revisemy_disk');
        $driver = (string) config("filesystems.disks.{$disk}.driver");
        $this->check($driver === 's3', 'Screenshots are kept in object storage.', "Screenshots are kept on the {$disk} disk ({$driver}), which a deploy can throw away. Set REVISEMY_DISK to an object storage disk.");
        $capture = config('revisemy.capture.driver');
        if ($capture === 'hosted') {
            $this->check(filled(config('revisemy.capture.endpoint')) && filled(config('revisemy.capture.api_key')), 'URL and email capture is set up.', 'REVISEMY_CAPTURE_DRIVER is hosted, but the endpoint or key is blank, so capture_url and html fail.');
        } else {
            $this->note($capture ? "Capture runs on {$capture}." : 'capture_url and html are off (no REVISEMY_CAPTURE_DRIVER). Screenshots and PDFs still work.');
        }

        $this->section('Jobs and the schedule');
        $this->queue();
        $beat = Cache::get(self::HEARTBEAT);
        $this->check(
            $beat !== null && Carbon::parse($beat)->gt(now()->subMinutes(3)),
            'The scheduler ran in the last few minutes.',
            'The scheduler hasn’t run lately, so expired reviews and their files are never pruned. Run php artisan schedule:run every minute.',
        );

        $this->section('Assistants');
        $this->check($this->passportKeys(), 'Assistants can Connect by signing in.', 'No Passport keys, so Connect fails at the last step. Set PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY. Try tokens still work.');
        $this->note('They connect to '.url('/mcp/revisemy'));

        $this->section('Errors');
        $this->check(config('nightwatch.enabled') && filled(config('nightwatch.token')), 'Errors reach Nightwatch.', 'Nightwatch is off, so an error reaches only the log. Set NIGHTWATCH_ENABLED and NIGHTWATCH_TOKEN.');

        $this->section('Optional');
        $this->note(filled(config('revisemy.anthropic.api_key')) || filled(config('revisemy.openai.api_key')) || filled(config('revisemy.openai.base_url'))
            ? 'Second opinion draws vision regions.'
            : 'Second opinion is the checklist alone (no ANTHROPIC_API_KEY or OPENAI_API_KEY).');
        $this->note(config('broadcasting.default') === 'reverb' && filled(config('reverb.apps.apps.0.key'))
            ? 'Marks and the board update live (Reverb).'
            : 'The review and board poll instead of updating live (no Reverb).');
        if (config('billing.pricing_enabled')) {
            $this->check(
                filled(config('billing.polar.access_token')) && filled(config('billing.polar.webhook_secret')) && filled(config('billing.polar.products.plus')),
                'Plus can be bought through Polar.',
                'Pricing is on, but POLAR_ACCESS_TOKEN, POLAR_WEBHOOK_SECRET or POLAR_PRODUCT_PLUS is blank, so checkout fails.',
            );
        } else {
            $this->note('Paid Plus is paused (REVISEMY_PRICING_ENABLED=false).');
        }

        $this->newLine();
        $this->line($this->missing ? 'Not ready yet: each ✗ above is one thing to do.' : 'Ready.');

        return $this->missing ? self::FAILURE : self::SUCCESS;
    }

    private function appUrl(): void
    {
        $url = (string) config('app.url');
        $host = (string) parse_url($url, PHP_URL_HOST);
        $real = str_starts_with($url, 'https://') && ! in_array($host, ['localhost', '127.0.0.1', ''], true) && ! str_ends_with($host, '.test');

        $this->check($real, "ReviseMy answers at {$url}.", "APP_URL is {$url}. Every review link, MCP URL and sign-in redirect is built from it.");
    }

    private function queue(): void
    {
        $connection = (string) config('queue.default');

        if ($connection === 'sync') {
            $this->note('Jobs run inside the request (QUEUE_CONNECTION=sync): fine without webhooks, slower with them.');

            return;
        }

        if ($connection === 'database' && Schema::hasTable('jobs')) {
            $stuck = DB::table('jobs')->where('available_at', '<', now()->subMinutes(5)->getTimestamp())->count();
            $this->check($stuck === 0, 'Jobs are being taken off the queue.', "{$stuck} ".($stuck === 1 ? 'job has' : 'jobs have').' waited over five minutes, so no worker is taking them. Run php artisan queue:work.');
        } else {
            $this->note("Jobs queue on {$connection}.");
        }

        if (Schema::hasTable('failed_jobs') && ($failed = DB::table('failed_jobs')->count()) > 0) {
            $this->note("{$failed} failed ".($failed === 1 ? 'job is' : 'jobs are').' kept. php artisan queue:failed lists them.');
        }
    }

    private function passportKeys(): bool
    {
        return (filled(config('passport.private_key')) || is_file(Passport::keyPath('oauth-private.key')))
            && (filled(config('passport.public_key')) || is_file(Passport::keyPath('oauth-public.key')));
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("<options=bold>{$title}</>");
    }

    private function check(bool $ready, string $readyLine, string $missingLine): void
    {
        if (! $ready) {
            $this->missing = true;
        }

        $this->line($ready ? "  <fg=green>✓</> {$readyLine}" : "  <fg=red>✗</> {$missingLine}");
    }

    private function note(string $line): void
    {
        $this->line("  · {$line}");
    }
}
