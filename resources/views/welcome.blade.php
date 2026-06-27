<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    @include('partials.head', ['title' => 'HitsTrack'])
</head>

<body class="min-h-screen bg-[#080b12] text-white antialiased">
    <div class="relative isolate min-h-screen overflow-hidden">
        <div
            class="pointer-events-none absolute inset-0 opacity-55 [background-image:linear-gradient(rgba(152,164,179,.13)_1px,transparent_1px),linear-gradient(90deg,rgba(152,164,179,.13)_1px,transparent_1px)] [background-size:72px_72px]">
        </div>
        <div class="pointer-events-none absolute inset-x-0 top-0 h-32 border-b border-white/10 bg-[#080b12]/85"></div>

        <header class="relative z-20 mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-5 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <x-app-logo-icon class="size-9" />
                <span class="text-lg font-semibold tracking-tight">HitsTrack</span>
            </a>

            <nav class="hidden items-center gap-8 text-sm text-zinc-300 md:flex">
                <a href="#trackers" class="transition hover:text-white">Trackers</a>
                <a href="#rotators" class="transition hover:text-white">Rotators</a>
                <a href="#stats" class="transition hover:text-white">Stats</a>
            </nav>

            <div class="flex items-center gap-3 text-sm">
                @auth
                <a href="{{ route('linktrackers') }}"
                    class="rounded-md bg-white px-4 py-2 font-medium text-zinc-950 transition hover:bg-zinc-200">
                    Open app
                </a>
                @else
                @if (Route::has('login'))
                <a href="{{ route('login') }}" class="hidden text-zinc-300 transition hover:text-white sm:inline">
                    Sign in
                </a>
                @endif

                @if (Route::has('register'))
                <a href="{{ route('register') }}"
                    class="rounded-md bg-white px-4 py-2 font-medium text-zinc-950 transition hover:bg-zinc-200">
                    Start tracking
                </a>
                @endif
                @endauth
            </div>
        </header>

        <main class="relative z-10">
            <section class="mx-auto grid min-h-[86vh] w-full max-w-7xl items-center gap-12 px-6 pb-16 pt-14 lg:grid-cols-[1fr_1.05fr] lg:px-8">
                <div class="max-w-2xl">
                    <p class="mb-5 inline-flex rounded-md border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-sm font-medium text-emerald-200">
                        Link tracking, rotation, and referrer analytics
                    </p>

                    <h1 class="text-5xl font-semibold tracking-tight text-white sm:text-6xl lg:text-7xl">
                        HitsTrack
                    </h1>

                    <p class="mt-6 max-w-xl text-lg leading-8 text-zinc-300">
                        Create short tracker links, rotate traffic between targets, and see which referrers bring real
                        visits without burying the work in dashboards.
                    </p>

                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        @auth
                        <a href="{{ route('linktrackers') }}"
                            class="inline-flex items-center justify-center rounded-md bg-emerald-400 px-5 py-3 text-sm font-semibold text-zinc-950 transition hover:bg-emerald-300">
                            Go to trackers
                        </a>
                        @else
                        @if (Route::has('register'))
                        <a href="{{ route('register') }}"
                            class="inline-flex items-center justify-center rounded-md bg-emerald-400 px-5 py-3 text-sm font-semibold text-zinc-950 transition hover:bg-emerald-300">
                            Create an account
                        </a>
                        @endif

                        @if (Route::has('login'))
                        <a href="{{ route('login') }}"
                            class="inline-flex items-center justify-center rounded-md border border-white/15 px-5 py-3 text-sm font-semibold text-white transition hover:border-white/30 hover:bg-white/10">
                            Sign in
                        </a>
                        @endif
                        @endauth
                    </div>
                </div>

                <div class="relative min-h-[520px]" aria-hidden="true">
                    <div class="absolute inset-0 rounded-lg border border-white/10 bg-[#141b26]/90 shadow-2xl shadow-black/40">
                        <div class="flex items-center gap-2 border-b border-white/10 px-5 py-4">
                            <span class="size-2.5 rounded-full bg-red-400"></span>
                            <span class="size-2.5 rounded-full bg-amber-300"></span>
                            <span class="size-2.5 rounded-full bg-emerald-400"></span>
                            <span class="ml-3 text-xs font-medium text-zinc-400">live routing board</span>
                        </div>

                        <div class="grid gap-4 p-5">
                            <div class="rounded-md border border-sky-400/25 bg-sky-400/10 p-4">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-medium text-sky-100">/t/A7kP2q</span>
                                    <span class="rounded bg-sky-400/20 px-2 py-1 text-xs text-sky-100">LinkTracker</span>
                                </div>
                                <div class="mt-4 h-2 rounded-full bg-[#202936]">
                                    <div class="h-2 w-4/5 rounded-full bg-sky-400"></div>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-3">
                                <div class="rounded-md border border-white/10 bg-white/[.04] p-4">
                                    <p class="text-xs text-zinc-400">Total hits</p>
                                    <p class="mt-2 text-2xl font-semibold text-white">18,492</p>
                                </div>
                                <div class="rounded-md border border-white/10 bg-white/[.04] p-4">
                                    <p class="text-xs text-zinc-400">Unique</p>
                                    <p class="mt-2 text-2xl font-semibold text-white">9,814</p>
                                </div>
                                <div class="rounded-md border border-white/10 bg-white/[.04] p-4">
                                    <p class="text-xs text-zinc-400">CTR lift</p>
                                    <p class="mt-2 text-2xl font-semibold text-emerald-300">24%</p>
                                </div>
                            </div>

                            <div class="rounded-md border border-white/10 bg-[#080b12]/70 p-5">
                                <div class="flex h-44 items-end gap-2">
                                    @foreach (['h-[36px]', 'h-[52px]', 'h-[46px]', 'h-[72px]', 'h-[65px]', 'h-[88px]', 'h-[76px]', 'h-[96px]', 'h-[84px]', 'h-[112px]', 'h-[104px]', 'h-[124px]'] as $heightClass)
                                    <div class="flex flex-1 items-end">
                                        <div class="{{ $heightClass }} w-full rounded-t bg-emerald-400/80"></div>
                                    </div>
                                    @endforeach
                                </div>
                                <div class="mt-5 flex items-center justify-between text-xs text-zinc-500">
                                    <span>Mon</span>
                                    <span>Tue</span>
                                    <span>Wed</span>
                                    <span>Thu</span>
                                    <span>Fri</span>
                                    <span>Sat</span>
                                    <span>Sun</span>
                                </div>
                            </div>

                            <div class="grid gap-3">
                                <div class="flex items-center justify-between rounded-md border border-white/10 bg-white/[.04] px-4 py-3">
                                    <span class="text-sm text-zinc-300">google.com</span>
                                    <span class="text-sm font-medium text-white">6,240 hits</span>
                                </div>
                                <div class="flex items-center justify-between rounded-md border border-white/10 bg-white/[.04] px-4 py-3">
                                    <span class="text-sm text-zinc-300">newsletter</span>
                                    <span class="text-sm font-medium text-white">3,918 hits</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-y border-white/10 bg-white/[.03]">
                <div class="mx-auto grid max-w-7xl gap-4 px-6 py-6 sm:grid-cols-3 lg:px-8">
                    <div>
                        <p class="text-3xl font-semibold text-white">6-char</p>
                        <p class="mt-1 text-sm text-zinc-400">automatic tracker slugs</p>
                    </div>
                    <div>
                        <p class="text-3xl font-semibold text-white">3 modes</p>
                        <p class="mt-1 text-sm text-zinc-400">random, weighted, round robin</p>
                    </div>
                    <div>
                        <p class="text-3xl font-semibold text-white">Daily</p>
                        <p class="mt-1 text-sm text-zinc-400">referrer and unique hit reports</p>
                    </div>
                </div>
            </section>

            <section id="trackers" class="mx-auto max-w-7xl px-6 py-20 lg:px-8">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-300">Trackers</p>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-white">Short links with useful context.</h2>
                    <p class="mt-4 text-zinc-400">
                        Each tracker gets a clean URL like <span class="font-mono text-zinc-200">/t/A7kP2q</span>, redirects to
                        the target, and records visits before sending traffic forward.
                    </p>
                </div>

                <div class="mt-10 grid gap-4 md:grid-cols-3">
                    <article class="rounded-md border border-white/10 bg-white/[.04] p-6">
                        <h3 class="font-semibold text-white">Automatic slugs</h3>
                        <p class="mt-3 text-sm leading-6 text-zinc-400">Random six-character links are generated for every tracker.</p>
                    </article>
                    <article class="rounded-md border border-white/10 bg-white/[.04] p-6">
                        <h3 class="font-semibold text-white">Target control</h3>
                        <p class="mt-3 text-sm leading-6 text-zinc-400">Edit destination URLs without changing the public tracker link.</p>
                    </article>
                    <article class="rounded-md border border-white/10 bg-white/[.04] p-6">
                        <h3 class="font-semibold text-white">Referrer insight</h3>
                        <p class="mt-3 text-sm leading-6 text-zinc-400">See where visits come from and compare total hits with unique hits.</p>
                    </article>
                </div>
            </section>

            <section id="rotators" class="border-y border-white/10 bg-[#101722]/90">
                <div class="mx-auto grid max-w-7xl gap-10 px-6 py-20 lg:grid-cols-[.8fr_1.2fr] lg:px-8">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-sky-300">Rotators</p>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-white">Route one link to many trackers.</h2>
                        <p class="mt-4 text-zinc-400">
                            Send traffic randomly, by weight, or in order. Perfect for testing offers, pages, or campaign
                            variants from one shareable URL.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-md border border-white/10 bg-[#080b12]/70 p-5">
                            <p class="text-sm font-medium text-white">Random</p>
                            <p class="mt-3 text-sm leading-6 text-zinc-400">Every attached tracker has a chance to receive the next visit.</p>
                        </div>
                        <div class="rounded-md border border-white/10 bg-[#080b12]/70 p-5">
                            <p class="text-sm font-medium text-white">Weighted</p>
                            <p class="mt-3 text-sm leading-6 text-zinc-400">Higher weight means more traffic over time.</p>
                        </div>
                        <div class="rounded-md border border-white/10 bg-[#080b12]/70 p-5">
                            <p class="text-sm font-medium text-white">Round robin</p>
                            <p class="mt-3 text-sm leading-6 text-zinc-400">Visits move through trackers in a predictable sequence.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="stats" class="mx-auto max-w-7xl px-6 py-20 lg:px-8">
                <div class="grid gap-10 lg:grid-cols-[1fr_1fr]">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-amber-300">Stats</p>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-white">Daily charts, clickable days, clean tables.</h2>
                        <p class="mt-4 text-zinc-400">
                            Open a tracker or rotator stats page, click a day on the line graph, and inspect referrer totals
                            with sortable hit counts.
                        </p>
                    </div>

                    <div class="rounded-md border border-white/10 bg-white/[.04] p-6">
                        <div class="space-y-4">
                            @foreach ([['Email launch', '4,812', '2,106'], ['Product Hunt', '2,904', '1,338'], ['Direct', '1,742', '1,221']] as $row)
                            <div class="grid grid-cols-3 items-center gap-4 border-b border-white/10 pb-4 last:border-b-0 last:pb-0">
                                <span class="text-sm text-zinc-300">{{ $row[0] }}</span>
                                <span class="text-right text-sm font-medium text-white">{{ $row[1] }}</span>
                                <span class="text-right text-sm font-medium text-emerald-300">{{ $row[2] }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <x-app-footer flux />
    </div>

    @fluxScripts
</body>

</html>
