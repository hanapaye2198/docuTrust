<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocuTrust | Premium Digital Signing Platform</title>
    <script>
        window.tailwind = window.tailwind || {};
        window.tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Inter", sans-serif; }
        .ambient-gradient { background-size: 220% 220%; animation: ambientShift 18s ease-in-out infinite; }
        .reveal { opacity: 0; transform: translateY(18px); transition: opacity .7s ease, transform .7s ease; }
        .reveal.visible { opacity: 1; transform: translateY(0); }
        .float-soft { animation: floatSoft 4s ease-in-out infinite; }
        .hero-glow { animation: heroGlow 6s ease-in-out infinite; }
        @keyframes ambientShift { 0% { background-position: 0 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }
        @keyframes floatSoft { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
        @keyframes heroGlow { 0%,100% { opacity: .5; transform: scale(1); } 50% { opacity: .9; transform: scale(1.07); } }
    </style>
    <script>
        (function () {
            const savedTheme = localStorage.getItem("theme");
            const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
            if (savedTheme === "dark" || (!savedTheme && prefersDark)) document.documentElement.classList.add("dark");
        })();
    </script>
</head>
<body class="bg-[#F8FAFC] text-[#1F2937] antialiased transition-colors duration-300 dark:bg-slate-950 dark:text-slate-100">
    @php
        $loginUrl = Route::has('login') ? route('login') : url('/');
        $registerUrl = Route::has('register') ? route('register') : $loginUrl;
        $dashboardUrl = auth()->check()
            ? route(auth()->user()->homeRouteName())
            : (Route::has('dashboard') ? route('dashboard') : url('/'));
    @endphp

    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="ambient-gradient absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(46,196,182,0.18),transparent_38%),radial-gradient(circle_at_bottom_left,rgba(255,209,102,0.2),transparent_35%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(46,196,182,0.22),transparent_40%),radial-gradient(circle_at_bottom_left,rgba(27,94,32,0.25),transparent_35%)]"></div>
    </div>

    <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/85 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/80">
        <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200 dark:bg-transparent dark:shadow-none dark:ring-slate-600/40">
                    <img src="{{ asset('images/docutrust-logo.png') }}" alt="DocuTrust logo" class="h-full w-full object-contain" />
                </span>
                <span class="text-xl font-bold">DocuTrust</span>
            </a>
            <nav class="hidden items-center gap-8 text-sm font-medium md:flex">
                <a href="#features" class="hover:text-[#2EC4B6]">Features</a>
                <a href="#about" class="hover:text-[#2EC4B6]">About</a>
                <a href="#industries" class="hover:text-[#2EC4B6]">Industries</a>
                <a href="#insights" class="hover:text-[#2EC4B6]">Insights</a>
                <a href="#faq" class="hover:text-[#2EC4B6]">FAQ</a>
            </nav>
            <div class="flex items-center gap-3">
                <button id="theme-toggle" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:border-[#2EC4B6] hover:text-[#2EC4B6] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300" aria-label="Toggle theme">
                    <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M21 12.79A9 9 0 1111.21 3c.5 0 .8.54.53.95A7 7 0 0019.05 12c.42-.27.95.03.95.53v.26z"></path></svg>
                    <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.364 6.364l-1.414-1.414M7.05 7.05 5.636 5.636m12.728 0L16.95 7.05M7.05 16.95l-1.414 1.414M12 16a4 4 0 100-8 4 4 0 000 8z"></path></svg>
                </button>
                @auth
                    <a href="{{ $dashboardUrl }}" class="rounded-xl bg-[#1B5E20] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#174f1b]">Dashboard</a>
                @else
                    <a href="{{ $loginUrl }}" class="hidden text-sm font-semibold sm:inline-flex">Login</a>
                    <a href="{{ $registerUrl }}" class="rounded-xl bg-[#2EC4B6] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1B5E20]">Get Started</a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        <section class="relative overflow-hidden pb-20 pt-16 sm:pt-20 lg:pt-24">
            <div class="mx-auto grid w-full max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
                <div class="reveal space-y-8" data-delay="0">
                    <span class="reveal inline-flex items-center gap-2 rounded-full border border-teal-200 bg-teal-50 px-4 py-1.5 text-sm font-semibold text-teal-700 dark:border-teal-500/30 dark:bg-teal-500/10 dark:text-teal-300" data-delay="80">
                        Trusted by 10,000+ teams worldwide
                    </span>
                    <h1 class="reveal text-balance text-4xl font-extrabold leading-tight text-slate-900 sm:text-5xl lg:text-6xl dark:text-white" data-delay="140">
                        Modern agreement workflows with enterprise-grade trust.
                    </h1>
                    <p class="reveal max-w-xl text-lg text-slate-600 dark:text-slate-300" data-delay="220">
                        Send, sign, and manage contracts in minutes with a beautifully simple experience that feels fast in both light and dark mode.
                    </p>
                    <div class="reveal flex flex-col gap-3 sm:flex-row" data-delay="300">
                        @auth
                            <a href="{{ $dashboardUrl }}" class="inline-flex items-center justify-center rounded-xl bg-teal-500 px-6 py-3.5 text-base font-semibold text-white shadow-lg shadow-teal-500/35 transition duration-300 hover:-translate-y-1 hover:bg-teal-400 hover:shadow-xl">
                                Go to dashboard
                            </a>
                        @else
                            <a href="{{ $registerUrl }}" class="inline-flex items-center justify-center rounded-xl bg-teal-500 px-6 py-3.5 text-base font-semibold text-white shadow-lg shadow-teal-500/35 transition duration-300 hover:-translate-y-1 hover:bg-teal-400 hover:shadow-xl">
                                Start free trial
                            </a>
                        @endauth
                        <a href="#demo" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-6 py-3.5 text-base font-semibold text-slate-700 transition duration-300 hover:-translate-y-1 hover:border-teal-400 hover:text-teal-600 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-teal-500 dark:hover:text-teal-300">
                            See product tour
                        </a>
                    </div>
                    <div class="reveal flex flex-wrap items-center gap-6 text-sm text-slate-500 dark:text-slate-400" data-delay="380">
                        <span>No credit card needed</span>
                        <span>14-day trial</span>
                        <span>24/7 support</span>
                    </div>
                </div>

                <div class="reveal relative float-soft" data-delay="180">
                    <div class="hero-glow absolute -left-8 -top-10 h-44 w-44 rounded-full bg-teal-400/30 blur-3xl dark:bg-teal-500/20"></div>
                    <div class="hero-glow absolute -bottom-10 -right-8 h-44 w-44 rounded-full bg-indigo-400/30 blur-3xl dark:bg-indigo-500/20" style="animation-delay: 1.5s;"></div>
                    <div class="relative rounded-3xl border border-white/60 bg-white/90 p-4 shadow-2xl shadow-slate-300/30 backdrop-blur dark:border-slate-800 dark:bg-slate-900/90 dark:shadow-black/30">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6 dark:border-slate-800 dark:bg-slate-950">
                            <div class="mb-6 flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">New request</p>
                                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Vendor Agreement.pdf</h3>
                                </div>
                                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">Ready to sign</span>
                            </div>
                            <div class="space-y-4">
                                <div class="rounded-xl border border-dashed border-slate-300 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                                    <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-200">Signers</p>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex items-center justify-between rounded-lg bg-slate-100 px-3 py-2 dark:bg-slate-800">
                                            <span class="text-slate-700 dark:text-slate-200">Maya Turner</span>
                                            <span class="font-semibold text-emerald-600 dark:text-emerald-300">Signed</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-lg bg-slate-100 px-3 py-2 dark:bg-slate-800">
                                            <span class="text-slate-700 dark:text-slate-200">Aron Diaz</span>
                                            <span class="font-semibold text-amber-600 dark:text-amber-300">Pending</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                    <div class="h-full w-2/3 rounded-full bg-teal-500"></div>
                                </div>
                                <p class="text-sm text-slate-600 dark:text-slate-300">67% completed - live reminders sent automatically.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-y border-slate-200 bg-white/70 py-10 dark:border-slate-800 dark:bg-slate-900/40">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="mb-6 text-center text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Trusted by teams, institutions, and organizations</p>
                <div class="grid grid-cols-2 gap-4 text-center sm:grid-cols-5">
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold dark:border-slate-800 dark:bg-slate-900">CivicCore</div>
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold dark:border-slate-800 dark:bg-slate-900">UniTrust</div>
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold dark:border-slate-800 dark:bg-slate-900">LegalGrid</div>
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold dark:border-slate-800 dark:bg-slate-900">HomeAxis</div>
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold dark:border-slate-800 dark:bg-slate-900">FinPulse</div>
                </div>
            </div>
        </section>

        <section class="py-20" id="features">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="reveal text-xs font-semibold uppercase tracking-[0.2em] text-[#2EC4B6] dark:text-[#7ce8dc]">Features</p>
                    <h2 class="reveal mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">Everything You Need to Manage Documents with Confidence</h2>
                    <p class="reveal mx-auto mt-4 max-w-2xl text-base text-slate-600 dark:text-slate-300">From legally binding signatures to audit-ready trails—one platform built for teams that cannot afford gaps in security or speed.</p>
                </div>
                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <article class="reveal group relative overflow-hidden rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white via-teal-50/40 to-[#2EC4B6]/10 p-8 shadow-lg shadow-slate-200/50 ring-2 ring-[#2EC4B6]/35 transition duration-300 hover:-translate-y-[5px] hover:border-[#2EC4B6] hover:shadow-2xl hover:shadow-[#2EC4B6]/25 dark:border-slate-700 dark:from-slate-900 dark:via-slate-900 dark:to-[#2EC4B6]/10 dark:shadow-black/40 dark:ring-[#2EC4B6]/45 dark:hover:shadow-[#2EC4B6]/20">
                        <span class="absolute right-4 top-4 rounded-full bg-[#2EC4B6] px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-md shadow-[#2EC4B6]/40">Most Used</span>
                        <span class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[#2EC4B6]/15 text-[#1B5E20] dark:text-[#7ce8dc]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </span>
                        <h3 class="pr-16 font-semibold text-lg text-slate-900 dark:text-white">Secure Digital Signing</h3>
                        <p class="mt-2 text-sm text-slate-400 dark:text-slate-400">Legally binding digital signatures with advanced encryption and verification.</p>
                    </article>
                    <article class="reveal group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-br from-white via-slate-50/90 to-white p-6 shadow-md backdrop-blur-sm transition duration-300 hover:-translate-y-[5px] hover:border-[#2EC4B6] hover:shadow-xl hover:shadow-[#2EC4B6]/10 dark:border-slate-700 dark:from-slate-900 dark:via-slate-900/95 dark:to-slate-900 dark:hover:shadow-[#2EC4B6]/15">
                        <span class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[#2EC4B6]/15 text-[#1B5E20] dark:text-[#7ce8dc]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </span>
                        <h3 class="font-semibold text-lg text-slate-900 dark:text-white">Multi-Signer Workflow</h3>
                        <p class="mt-2 text-sm text-slate-400 dark:text-slate-400">Define signing order, approvals, and roles with ease.</p>
                    </article>
                    <article class="reveal group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-br from-white via-slate-50/90 to-white p-6 shadow-md backdrop-blur-sm transition duration-300 hover:-translate-y-[5px] hover:border-[#2EC4B6] hover:shadow-xl hover:shadow-[#2EC4B6]/10 dark:border-slate-700 dark:from-slate-900 dark:via-slate-900/95 dark:to-slate-900 dark:hover:shadow-[#2EC4B6]/15">
                        <span class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[#2EC4B6]/15 text-[#1B5E20] dark:text-[#7ce8dc]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </span>
                        <h3 class="font-semibold text-lg text-slate-900 dark:text-white">Real-Time Tracking</h3>
                        <p class="mt-2 text-sm text-slate-400 dark:text-slate-400">Monitor document status from sent to signed in real time.</p>
                    </article>
                    <article class="reveal group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-br from-white via-slate-50/90 to-white p-6 shadow-md backdrop-blur-sm transition duration-300 hover:-translate-y-[5px] hover:border-[#2EC4B6] hover:shadow-xl hover:shadow-[#2EC4B6]/10 dark:border-slate-700 dark:from-slate-900 dark:via-slate-900/95 dark:to-slate-900 dark:hover:shadow-[#2EC4B6]/15">
                        <span class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[#2EC4B6]/15 text-[#1B5E20] dark:text-[#7ce8dc]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                        </span>
                        <h3 class="font-semibold text-lg text-slate-900 dark:text-white">Blockchain Verification</h3>
                        <p class="mt-2 text-sm text-slate-400 dark:text-slate-400">Ensure tamper-proof and verifiable document authenticity.</p>
                    </article>
                    <article class="reveal group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-br from-white via-slate-50/90 to-white p-6 shadow-md backdrop-blur-sm transition duration-300 hover:-translate-y-[5px] hover:border-[#2EC4B6] hover:shadow-xl hover:shadow-[#2EC4B6]/10 dark:border-slate-700 dark:from-slate-900 dark:via-slate-900/95 dark:to-slate-900 dark:hover:shadow-[#2EC4B6]/15">
                        <span class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[#2EC4B6]/15 text-[#1B5E20] dark:text-[#7ce8dc]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                        </span>
                        <h3 class="font-semibold text-lg text-slate-900 dark:text-white">Smart Document Management</h3>
                        <p class="mt-2 text-sm text-slate-400 dark:text-slate-400">Organize, search, and access files instantly with intelligent tagging.</p>
                    </article>
                    <article class="reveal group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-br from-white via-slate-50/90 to-white p-6 shadow-md backdrop-blur-sm transition duration-300 hover:-translate-y-[5px] hover:border-[#2EC4B6] hover:shadow-xl hover:shadow-[#2EC4B6]/10 dark:border-slate-700 dark:from-slate-900 dark:via-slate-900/95 dark:to-slate-900 dark:hover:shadow-[#2EC4B6]/15">
                        <span class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[#2EC4B6]/15 text-[#1B5E20] dark:text-[#7ce8dc]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                        </span>
                        <h3 class="font-semibold text-lg text-slate-900 dark:text-white">Audit Trail Logs</h3>
                        <p class="mt-2 text-sm text-slate-400 dark:text-slate-400">Complete history of every action for transparency and compliance.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="py-20" id="about">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                    <div class="reveal text-left">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">About DocuTrust</p>
                        <h2 class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">Powering Trust Through Innovation</h2>
                        <div class="mt-6 max-w-xl text-slate-600 dark:text-slate-300">
                            <p>
                                DocuTrust is a next-generation digital signing platform built to transform how organizations manage agreements, approvals, and document workflows. Designed for speed, security, and scalability, it empowers teams to move faster while maintaining complete trust in every transaction.
                            </p>
                            <p class="mt-4">
                                Backed by <span class="font-semibold text-slate-800 dark:text-slate-100">Surepay Technologies Inc.</span>, a <span class="font-semibold text-[#1B5E20] dark:text-[#7ce8dc]">BSP-licensed</span> payment service operator and a trusted fintech company in the Philippines, DocuTrust is built on a foundation of <span class="font-semibold text-[#1B5E20] dark:text-[#7ce8dc]">bank-grade security</span>, reliability, and innovation.
                            </p>
                            <p class="mt-4">
                                <span class="font-semibold text-slate-800 dark:text-slate-100">Surepay Technologies Inc.</span> is recognized for delivering secure and scalable digital service solutions—enabling institutions to streamline transactions, improve operational accuracy, and deliver seamless customer experiences.
                            </p>
                            <p class="mt-4">
                                Through major initiatives such as the LGU Integrated Financial Tools (LIFT) system, ISO 9001:2015 certified operations, and nationwide digital transformation programs, Surepay continues to lead in building secure and efficient digital ecosystems.
                            </p>
                            <p class="mt-4">
                                With DocuTrust, organizations gain more than just digital signatures—they gain a complete, secure, and intelligent document workflow solution built for modern enterprise needs.
                            </p>
                        </div>
                    </div>
                    <div class="reveal" data-delay="120">
                        @if (file_exists(public_path('images/about-us.jpg')))
                            <div class="aspect-[4/3] overflow-hidden rounded-2xl shadow-xl">
                                <img src="{{ asset('images/about-us.jpg') }}" alt="About DocuTrust" class="h-full w-full object-cover" width="800" height="600" loading="lazy" />
                            </div>
                        @else
                            <div class="relative flex aspect-[4/3] items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-[#2EC4B6]/12 via-slate-50 to-[#1B5E20]/10 shadow-xl dark:from-[#2EC4B6]/10 dark:via-slate-900 dark:to-[#1B5E20]/20">
                                <div class="p-8 text-center">
                                    <span class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-[#2EC4B6]/20 text-[#1B5E20] dark:bg-[#2EC4B6]/15 dark:text-[#7ce8dc]">
                                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </span>
                                    <p class="mt-4 text-sm font-medium text-slate-500 dark:text-slate-400">Image placeholder</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="py-20 bg-white/70 dark:bg-slate-900/40" id="demo">
            <div class="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                <h2 class="reveal text-3xl font-bold sm:text-4xl">See DocuTrust in Action</h2>
                <p class="reveal mx-auto mt-4 max-w-2xl text-slate-600 dark:text-slate-300">Experience how simple and secure digital signing can be.</p>
                <div class="reveal mx-auto mt-10 max-w-4xl rounded-3xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                    <div class="aspect-video w-full overflow-hidden rounded-2xl bg-slate-900/5 ring-1 ring-slate-200/80 dark:bg-slate-800/30 dark:ring-slate-700/80">
                        <iframe
                            class="h-full w-full rounded-2xl"
                            src="https://www.youtube.com/embed/aRFZeahiA4w"
                            title="DocuTrust Demo Video"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-20 bg-white/70 dark:bg-slate-900/40" id="industries">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="reveal text-center text-3xl font-bold sm:text-4xl">Built for Every Industry</h2>
                <div class="mt-10 grid grid-cols-2 gap-4 text-center sm:grid-cols-3 lg:grid-cols-6">
                    @foreach (['Government','Education','Legal','Real Estate','HR & Recruitment','Finance'] as $industry)
                        <div class="reveal rounded-xl border border-slate-200 bg-white px-4 py-3 font-semibold dark:border-slate-800 dark:bg-slate-900">{{ $industry }}</div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-3xl bg-[#1B5E20] p-10 text-white">
                    <h2 class="reveal text-center text-3xl font-bold sm:text-4xl">Improve Efficiency and Reduce Costs</h2>
                    <div class="mt-10 grid gap-6 text-center sm:grid-cols-2 lg:grid-cols-4">
                        @foreach (['80% Faster Document Processing','60% Less Paper Usage','40% Cost Reduction','100% Secure Transactions'] as $metric)
                            <div class="reveal rounded-2xl border border-white/20 bg-white/10 p-5">{{ $metric }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section class="py-20 bg-white/70 dark:bg-slate-900/40" id="insights">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="reveal text-center text-3xl font-bold sm:text-4xl">Insights & Updates</h2>
                <div class="mt-10 grid gap-6 md:grid-cols-3">
                    @foreach ([['Digital transformation in document workflows','How modern teams sign faster with less friction.'],['Paperless operations at scale','Practical strategies for reducing cost and waste.'],['Security and compliance essentials','What to review before deploying e-signature platforms.']] as $post)
                        <article class="reveal rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-xl dark:border-slate-800 dark:bg-slate-900">
                            <h3 class="font-semibold">{{ $post[0] }}</h3>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $post[1] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="reveal text-center text-3xl font-bold sm:text-4xl">What Our Users Say</h2>
                <div class="mt-10 grid gap-6 md:grid-cols-3">
                    @foreach ([['"DocuTrust reduced our turnaround time dramatically."','Operations Lead, Civic Agency'],['"The audit trail and verification workflow are exactly what our legal team needed."','Partner, Legal Advisory Group'],['"Secure, intuitive, and fast onboarding for every signer."','HR Director, Enterprise Group']] as $quote)
                        <blockquote class="reveal rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <p class="font-medium">{{ $quote[0] }}</p>
                            <footer class="mt-4 text-sm text-slate-500 dark:text-slate-400">{{ $quote[1] }}</footer>
                        </blockquote>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="py-20 bg-white/70 dark:bg-slate-900/40" id="faq">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <h2 class="reveal text-center text-3xl font-bold sm:text-4xl">Frequently Asked Questions</h2>
                <div class="mt-10 space-y-4">
                    @foreach ([['What is DocuTrust?','DocuTrust is a secure digital signing platform that helps teams sign, verify, and manage documents online.'],['Is DocuTrust legally binding?','Yes. DocuTrust supports legally recognized digital signing flows designed for business and institutional compliance.'],['How secure is DocuTrust?','DocuTrust applies strong encryption, access controls, and tamper-evident verification for every signed document.'],['Can I use it for my organization?','Yes. Teams of any size can deploy DocuTrust for approvals, contracts, onboarding, and internal workflows.']] as $faq)
                        <details class="reveal rounded-xl border border-slate-200 bg-white p-5 group dark:border-slate-800 dark:bg-slate-900">
                            <summary class="cursor-pointer list-none font-semibold">{{ $faq[0] }}</summary>
                            <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ $faq[1] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="reveal rounded-3xl bg-gradient-to-r from-[#2EC4B6] to-[#1B5E20] p-12 text-center text-white shadow-2xl">
                    <h2 class="text-4xl font-bold">Start Signing Smarter Today</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-white/85">Move from manual paperwork to secure, trusted, and modern digital workflows.</p>
                    <div class="mt-8">
                        @auth
                            <a href="{{ $dashboardUrl }}" class="inline-flex rounded-xl bg-white px-8 py-3.5 font-semibold text-[#1B5E20] transition hover:bg-[#FFD166]">Go to Dashboard</a>
                        @else
                            <a href="{{ $registerUrl }}" class="inline-flex rounded-xl bg-white px-8 py-3.5 font-semibold text-[#1B5E20] transition hover:bg-[#FFD166]">Create Free Account</a>
                        @endauth
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white py-12 dark:border-slate-800 dark:bg-slate-950">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 md:grid-cols-4 lg:px-8">
            <div>
                <a href="{{ url('/') }}" class="inline-flex max-w-full items-center gap-2.5 sm:gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200 dark:bg-transparent dark:shadow-none dark:ring-slate-600/40 sm:h-10 sm:w-10">
                        <img src="{{ asset('images/docutrust-logo.png') }}" alt="DocuTrust logo" class="h-full w-full object-contain" width="40" height="40" loading="lazy" />
                    </span>
                    <span class="min-w-0 text-lg font-bold text-slate-900 dark:text-white">DocuTrust</span>
                </a>
                <p class="mt-2 max-w-xs text-sm text-slate-500 dark:text-slate-400">Secure digital signing for modern teams.</p>
            </div>
            <div>
                <h3 class="font-semibold">Product</h3>
                <ul class="mt-3 space-y-2 text-sm text-slate-500 dark:text-slate-400">
                    <li><a href="#" class="hover:text-[#2EC4B6]">Features</a></li>
                    <li><a href="#" class="hover:text-[#2EC4B6]">Security</a></li>
                    <li><a href="#" class="hover:text-[#2EC4B6]">Integrations</a></li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold">Company</h3>
                <ul class="mt-3 space-y-2 text-sm text-slate-500 dark:text-slate-400">
                    <li><a href="#about" class="hover:text-[#2EC4B6]">About</a></li>
                    <li><a href="#" class="hover:text-[#2EC4B6]">Blog</a></li>
                    <li><a href="#" class="hover:text-[#2EC4B6]">Contact</a></li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold">Support</h3>
                <ul class="mt-3 space-y-2 text-sm text-slate-500 dark:text-slate-400">
                    <li><a href="#" class="hover:text-[#2EC4B6]">Help Center</a></li>
                    <li><a href="#" class="hover:text-[#2EC4B6]">Privacy Policy</a></li>
                    <li><a href="#" class="hover:text-[#2EC4B6]">Terms</a></li>
                </ul>
            </div>
        </div>
        <div class="mt-10 text-center text-sm text-slate-500 dark:text-slate-400">
            DocuTrust is powered by
            <span class="font-semibold text-[#1B5E20] dark:text-[#2EC4B6]">
                Surepay Technologies Inc.
            </span>
        </div>
    </footer>

    <script>
        const themeToggleButton = document.getElementById("theme-toggle");
        const rootElement = document.documentElement;
        const setThemeState = (isDarkMode) => {
            themeToggleButton?.setAttribute("aria-pressed", isDarkMode ? "true" : "false");
            themeToggleButton?.setAttribute("title", isDarkMode ? "Switch to light mode" : "Switch to dark mode");
        };
        setThemeState(rootElement.classList.contains("dark"));
        themeToggleButton?.addEventListener("click", function () {
            const isDark = rootElement.classList.toggle("dark");
            localStorage.setItem("theme", isDark ? "dark" : "light");
            setThemeState(isDark);
        });

        const revealItems = document.querySelectorAll(".reveal");
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const delay = entry.target.getAttribute("data-delay");
                    if (delay) {
                        entry.target.style.transitionDelay = `${delay}ms`;
                    }
                    entry.target.classList.add("visible");
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });
        revealItems.forEach((item) => revealObserver.observe(item));
    </script>
</body>
</html>
