{{-- The wait while a review is made from a form that posts to
     reviews.submit (/new and the home hero). Goes inside the form, whose
     x-data is reviewSubmit(kind). The form posts in the background and the
     server streams each step ToolProgress hears (SubmitReviewController::
     streamSteps), so the rows tick off as each viewport really lands.
     Without JS, or on a reply that isn't the stream, the form posts as usual. --}}
@php
    $sizes = collect(app(\App\Services\Capture\PageCaptureService::class)->viewports())
        ->map(function (array $viewport) {
            $name = explode('-', $viewport['label'], 2)[0];

            return [
                'key' => 'shot:'.$viewport['label'],
                'label' => ($name === 'mobile' ? 'Phone' : ucfirst($name)).' · '.$viewport['width'].'px',
                'order' => ['desktop' => 0, 'tablet' => 1, 'mobile' => 2][$name] ?? 3,
            ];
        })
        ->sortBy('order')
        ->values()
        ->map(fn (array $size) => ['key' => $size['key'], 'label' => $size['label']])
        ->all();
    $dot = \App\Models\Annotation::TONES['agent']['dot'];
@endphp

<template x-teleport="body">
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="review-progress-title"
    >
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
            aria-hidden="true"
        ></div>

        <div
            x-show="open"
            x-trap.noscroll.noautofocus="open"
            tabindex="-1"
            x-transition:enter="transition duration-300 ease-[cubic-bezier(0.23,1,0.32,1)]"
            x-transition:enter-start="opacity-0 scale-[0.96]"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition duration-150 ease-out"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-[0.96]"
            class="relative w-full max-w-sm rounded-2xl bg-card p-5 shadow-xl ring-1 ring-black/5"
        >
            {{-- Working --}}
            <div x-show="state === 'working'" role="status" aria-live="polite">
                <div class="flex items-baseline gap-3">
                    <h2 id="review-progress-title" class="min-w-0 flex-1 truncate text-base font-semibold text-zinc-900" x-text="title"></h2>
                    <span class="shrink-0 text-xs tabular-nums text-zinc-500" x-show="elapsed >= 3" x-text="elapsedLabel()"></span>
                </div>
                <p class="mt-1 text-sm text-pretty text-zinc-500" x-show="detail" x-text="detail"></p>

                <ol class="mt-4 space-y-2.5">
                    <template x-for="row in rows" :key="row.key">
                        <li class="flex items-center gap-3 text-sm">
                            <span class="flex size-4 shrink-0 items-center justify-center" aria-hidden="true">
                                <template x-if="row.state === 'done'">
                                    <flux:icon.check variant="micro" class="size-4 text-done-ink" />
                                </template>
                                <template x-if="row.state === 'working'">
                                    <span class="size-2 animate-pulse rounded-full {{ $dot }}"></span>
                                </template>
                                <template x-if="row.state === 'waiting'">
                                    <span class="size-2 rounded-full ring-1 ring-zinc-300"></span>
                                </template>
                            </span>
                            <span x-text="row.label" :class="row.state === 'waiting' ? 'text-zinc-400' : 'text-zinc-800'"></span>
                            <span class="sr-only" x-text="row.state === 'done' ? 'done' : (row.state === 'working' ? 'in progress' : 'waiting')"></span>
                        </li>
                    </template>
                </ol>

                <p class="mt-4 text-xs text-muted-foreground">You can close this tab. The review still lands in <a href="/reviews" class="link">Your reviews</a>.</p>
            </div>

            {{-- Turned down: the reason, and the form as it was --}}
            <div x-show="state === 'failed'" role="alert">
                <h2 class="text-base font-semibold text-zinc-900">The review wasn’t made</h2>
                <p class="mt-1 text-sm text-pretty text-zinc-600" x-text="failure"></p>
                <button type="button" x-on:click="close()" class="btn-lit mt-4 inline-flex h-9 items-center justify-center rounded-full px-4 text-sm font-medium">Back to the form</button>
            </div>

            {{-- The stream ended without an answer --}}
            <div x-show="state === 'lost'" role="alert">
                <h2 class="text-base font-semibold text-zinc-900">Lost the connection</h2>
                <p class="mt-1 text-sm text-pretty text-zinc-600">The capture may still finish. Check <a href="/reviews" class="link">Your reviews</a> in a minute before you try again, so you don’t spend the credits twice.</p>
                <button type="button" x-on:click="close()" class="btn-lit mt-4 inline-flex h-9 items-center justify-center rounded-full px-4 text-sm font-medium">Back to the form</button>
            </div>
        </div>
    </div>
</template>

@once
    <script>
        function reviewSubmit(kind) {
            const SIZES = @js($sizes);
            const FIRST = {
                link: null,
                images: 'Saving the screenshots',
                pdf: 'Rendering the slides',
                html: 'Rendering the email',
            };

            return {
                kind,
                busy: false,
                open: false,
                state: 'working',
                title: '',
                detail: '',
                rows: [],
                errors: {},
                failure: '',
                elapsed: 0,
                timer: null,

                async start(event) {
                    const form = event.target;

                    if (!window.fetch || !window.ReadableStream) {
                        this.busy = true;
                        return;
                    }

                    event.preventDefault();
                    this.busy = true;
                    this.errors = {};
                    this.prepare(form);
                    this.open = true;
                    this.elapsed = 0;
                    this.timer = setInterval(() => this.elapsed++, 1000);

                    let response;
                    try {
                        response = await fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: { Accept: 'text/event-stream' },
                            credentials: 'same-origin',
                        });
                    } catch (e) {
                        return this.finish('lost');
                    }

                    const type = response.headers.get('Content-Type') || '';

                    if (response.status === 422 && type.includes('json')) {
                        const body = await response.json().catch(() => ({}));
                        this.errors = body.errors || {};
                        // A Turnstile answer works once; ask for a fresh one.
                        window.turnstile?.reset();
                        return this.close();
                    }

                    // Throttled, an expired page, anything else: let the
                    // server answer the way it does without JS.
                    if (!response.ok || !type.includes('text/event-stream')) {
                        this.stop();
                        return form.submit();
                    }

                    await this.read(response.body.getReader());
                },

                prepare(form) {
                    const page = (form.querySelector('[name=page_url]') || {}).value || '';
                    let host = '';
                    try {
                        host = new URL(/^[a-z][a-z0-9+.-]*:\/\//i.test(page) ? page : 'https://' + page).hostname.replace(/^www\./, '');
                    } catch (e) {
                        host = '';
                    }

                    this.state = 'working';
                    if (this.kind === 'link') {
                        this.title = 'Capturing ' + (host || 'the page');
                        this.detail = 'Desktop, tablet and phone at once, full page. This usually takes 20 to 60 seconds.';
                        this.rows = SIZES.map((size) => ({ ...size, state: 'working' }));
                    } else {
                        this.title = FIRST[this.kind] || 'Making the review';
                        this.detail = '';
                        this.rows = this.kind === 'images' ? [] : [{ key: 'render', label: FIRST[this.kind], state: 'working' }];
                    }
                    this.rows.push({ key: 'save', label: 'Saving the review', state: this.rows.length ? 'waiting' : 'working' });
                },

                async read(reader) {
                    const decoder = new TextDecoder();
                    let buffer = '';

                    while (true) {
                        let chunk;
                        try {
                            chunk = await reader.read();
                        } catch (e) {
                            return this.finish('lost');
                        }
                        if (chunk.done) {
                            return this.finish('lost');
                        }

                        buffer += decoder.decode(chunk.value, { stream: true });
                        let end;
                        while ((end = buffer.indexOf('\n\n')) !== -1) {
                            const frame = buffer.slice(0, end);
                            buffer = buffer.slice(end + 2);
                            const line = frame.split('\n').find((l) => l.startsWith('data: '));
                            if (!line) {
                                continue;
                            }
                            let event;
                            try {
                                event = JSON.parse(line.slice(6));
                            } catch (e) {
                                continue;
                            }
                            if (this.handle(event)) {
                                return;
                            }
                        }
                    }
                },

                // True once there's nothing more to wait for.
                handle(event) {
                    if (event.done) {
                        this.stop();
                        window.location.assign(event.done);
                        return true;
                    }
                    if (event.errors) {
                        this.failure = Object.values(event.errors)[0] || '';
                        this.finish('failed');
                        return true;
                    }

                    const row = this.rows.find((r) => r.key === event.step);
                    if (event.step === 'save') {
                        // Whatever hadn't reported has landed by now.
                        this.rows.forEach((r) => { if (r.key !== 'save') r.state = 'done'; });
                    }
                    if (row) {
                        row.state = event.step === 'save' || event.step === 'render' ? 'working' : 'done';
                    }
                    return false;
                },

                finish(state) {
                    this.stop();
                    this.state = state;
                },

                stop() {
                    clearInterval(this.timer);
                    this.timer = null;
                },

                close() {
                    this.stop();
                    this.open = false;
                    this.busy = false;
                },

                // The message for a field, from the JSON a 422 sent back.
                error(field) {
                    const key = Object.keys(this.errors).find((k) => k === field || k.startsWith(field + '.'));
                    return key ? [].concat(this.errors[key])[0] : '';
                },

                elapsedLabel() {
                    const m = Math.floor(this.elapsed / 60);
                    const s = String(this.elapsed % 60).padStart(2, '0');
                    return m + ':' + s;
                },
            };
        }
    </script>
@endonce
