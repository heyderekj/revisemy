<?php

use App\Services\TryTokenService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

new class extends Component
{
    public ?string $token = null;

    public ?string $mcpUrl = null;

    public ?string $cursorConfigJson = null;

    public ?string $error = null;

    public function getTryToken(TryTokenService $tryTokens): void
    {
        $this->error = null;

        $key = 'try-token-web:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->error = 'Slow down — try again in a minute.';

            return;
        }

        RateLimiter::hit($key, 60);

        $result = $tryTokens->create();

        $this->token = $result['token'];
        $this->mcpUrl = $result['mcp_url'];
        $this->cursorConfigJson = json_encode($result['cursor_config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
};
?>

<div class="relative overflow-hidden">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(15,118,110,0.12),_transparent_55%),linear-gradient(to_bottom,_#fafaf9,_#f4f4f5)] dark:bg-[radial-gradient(ellipse_at_top,_rgba(45,212,191,0.08),_transparent_55%),linear-gradient(to_bottom,_#09090b,_#18181b)]"></div>

    <div class="relative mx-auto flex min-h-screen max-w-3xl flex-col px-6 py-10 sm:py-16">
        <header class="mb-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-700 text-sm font-semibold text-white shadow-sm">R</div>
                <span class="text-lg font-semibold tracking-tight">ReviseMy</span>
            </div>
            <a href="https://github.com/heyderekj/revisemy" class="text-sm text-zinc-500 transition hover:text-zinc-800 dark:hover:text-zinc-200" target="_blank" rel="noreferrer">
                GitHub
            </a>
        </header>

        <main class="flex flex-1 flex-col justify-center">
            <p class="mb-4 text-sm font-medium uppercase tracking-[0.18em] text-teal-700 dark:text-teal-400">For agents &amp; designers</p>
            <h1 class="font-display max-w-xl text-5xl leading-[1.05] tracking-tight text-zinc-900 sm:text-6xl dark:text-zinc-50">
                Pin feedback for your agent.
            </h1>
            <p class="mt-6 max-w-lg text-lg leading-relaxed text-zinc-600 dark:text-zinc-400">
                Upload screenshots from any project, leave pins like a design critique, approve or request changes — your agent reads the structured notes over MCP.
            </p>

            <div class="mt-10 flex flex-wrap items-center gap-4">
                @if (! $token)
                    <flux:button variant="primary" wire:click="getTryToken" class="!bg-teal-700 hover:!bg-teal-800">
                        Get a try token
                    </flux:button>
                    <span class="text-sm text-zinc-500">Works with any project · no account</span>
                @endif
            </div>

            @if ($error)
                <flux:callout variant="danger" class="mt-8">{{ $error }}</flux:callout>
            @endif

            @if ($token)
                <div class="mt-12 space-y-6">
                    <flux:callout variant="success">
                        Your try token is ready. Paste this into Cursor (or any MCP host), then ask your agent to screenshot UI work and call <code class="font-mono text-sm">create_review</code>.
                    </flux:callout>

                    <div>
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <flux:heading size="sm">Cursor MCP config</flux:heading>
                            <flux:button size="sm" variant="ghost" x-data x-on:click="navigator.clipboard.writeText($refs.config.textContent)">
                                Copy
                            </flux:button>
                        </div>
                        <pre
                            x-ref="config"
                            class="overflow-x-auto rounded-xl border border-zinc-200 bg-zinc-950 p-4 text-sm leading-relaxed text-zinc-100 dark:border-zinc-800"
                        >{{ $cursorConfigJson }}</pre>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-zinc-200 bg-white/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">MCP URL</p>
                            <p class="mt-2 break-all font-mono text-sm">{{ $mcpUrl }}</p>
                        </div>
                        <div class="rounded-xl border border-zinc-200 bg-white/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Bearer token</p>
                            <p class="mt-2 break-all font-mono text-sm">{{ $token }}</p>
                        </div>
                    </div>

                    <ol class="space-y-3 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                        <li><span class="font-medium text-zinc-900 dark:text-zinc-100">1.</span> Add the config to Cursor MCP settings in any local project.</li>
                        <li><span class="font-medium text-zinc-900 dark:text-zinc-100">2.</span> Ask the agent to capture a UI screenshot and create a ReviseMy review.</li>
                        <li><span class="font-medium text-zinc-900 dark:text-zinc-100">3.</span> Open the <code class="font-mono">*.laravel.cloud</code> link, pin notes, then approve or request changes.</li>
                    </ol>
                </div>
            @endif
        </main>

        <footer class="mt-20 border-t border-zinc-200/80 pt-6 text-sm text-zinc-500 dark:border-zinc-800">
            Open source · Laravel + Livewire Flux · Built for Laravel Cloud
        </footer>
    </div>
</div>
