{{-- Passport's consent page, for a browser that's already connected once. The
     first Connect skips it (App\Models\OAuthClient::skipsAuthorization). --}}
<x-layouts.app title="Connect {{ $client->name }} — ReviseMy" robots="noindex, nofollow">
    @php($returnsTo = parse_url((string) $request->redirect_uri, PHP_URL_HOST) ?: (string) $request->redirect_uri)
    <div class="rm-desk flex min-h-svh flex-col">
        <main class="rm-shell items-center justify-center px-6 py-16">
            <div class="w-full max-w-lg">
                <x-revisemy-logo size="lg" />
                <h1 class="mt-6 text-2xl font-semibold text-foreground">Connect {{ $client->name }}</h1>
                <p class="mt-2 text-sm leading-relaxed text-pretty text-muted-foreground">
                    It works in the try workspace this browser is already connected to.
                </p>

                <x-connect-scope :name="$client->name" class="mt-6" />

                <div class="mt-8 flex flex-col gap-3">
                    <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                        @csrf
                        <input type="hidden" name="state" value="{{ $request->state }}">
                        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit" class="btn-lit inline-flex h-10 w-full items-center justify-center rounded-full text-sm font-medium">Connect {{ $client->name }}</button>
                    </form>
                    <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="state" value="{{ $request->state }}">
                        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-full bg-chip text-sm font-medium text-zinc-700 transition-colors hover:bg-chip-hover">Not now</button>
                    </form>
                    <p class="text-center text-xs text-muted-foreground">Returns you to <span class="font-medium text-zinc-700">{{ $returnsTo }}</span></p>
                </div>
            </div>
        </main>
    </div>
</x-layouts.app>
