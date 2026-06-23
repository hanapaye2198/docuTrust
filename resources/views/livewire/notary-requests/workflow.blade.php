<?php

use App\Models\NotaryRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public NotaryRequest $notaryRequest;

    public function mount(NotaryRequest $notaryRequest): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $notaryRequest->load(['documents', 'signers', 'sessions', 'payments', 'registerEntries']);

        Gate::authorize('view', $notaryRequest);

        $this->notaryRequest = $notaryRequest;
    }

    /**
     * @return list<array{label: string, description: string, href: string, icon: string, complete: bool, active: bool}>
     */
    #[Computed]
    public function workflowSteps(): array
    {
        $hasDocument = $this->notaryRequest->documents->isNotEmpty() || $this->notaryRequest->document_path !== null;
        $hasSigners = $this->notaryRequest->signers->isNotEmpty();
        $hasSession = $this->notaryRequest->sessions->isNotEmpty();
        $hasPayment = $this->notaryRequest->payments->isNotEmpty();
        $hasRegisterEntry = $this->notaryRequest->registerEntries->isNotEmpty();
        $isComplete = in_array($this->notaryRequest->status->value, ['digitalized', 'notarized'], true);

        return [
            [
                'label' => __('Prepare Document'),
                'description' => __('Upload the PDF, place fields, and prepare it for signing.'),
                'href' => route('notary.requests.show', [$this->notaryRequest, 'tab' => 'documents']),
                'icon' => 'document-text',
                'complete' => $hasDocument,
                'active' => ! $hasDocument,
            ],
            [
                'label' => __('Send to Client / Signers'),
                'description' => __('Confirm client, signer, and witness details before invitations go out.'),
                'href' => route('notary.requests.show', [$this->notaryRequest, 'tab' => 'parties']),
                'icon' => 'user-group',
                'complete' => $hasSigners,
                'active' => $hasDocument && ! $hasSigners,
            ],
            [
                'label' => __('Client Signs'),
                'description' => __('Track remote signatures and signer completion status.'),
                'href' => route('notary.requests.show', [$this->notaryRequest, 'tab' => 'documents']),
                'icon' => 'pencil-square',
                'complete' => false,
                'active' => $hasDocument && $hasSigners,
            ],
            [
                'label' => __('Video Verification'),
                'description' => __('Schedule or join the verification session and verify identity.'),
                'href' => route('notary.requests.show', [$this->notaryRequest, 'tab' => 'session']),
                'icon' => 'video-camera',
                'complete' => $hasSession,
                'active' => $hasSigners && ! $hasSession,
            ],
            [
                'label' => __('Payment'),
                'description' => __('Collect or confirm fees before final notarization.'),
                'href' => route('notary.requests.show', [$this->notaryRequest, 'tab' => 'closing']),
                'icon' => 'banknotes',
                'complete' => $hasPayment,
                'active' => $hasSession && ! $hasPayment,
            ],
            [
                'label' => __('Register & Seal'),
                'description' => __('Create the register entry, apply the notarial seal, and finalize artifacts.'),
                'href' => route('notary.requests.show', [$this->notaryRequest, 'tab' => 'closing']),
                'icon' => 'shield-check',
                'complete' => $hasRegisterEntry,
                'active' => $hasPayment && ! $hasRegisterEntry,
            ],
            [
                'label' => __('Complete Case'),
                'description' => __('Review final status, audit history, and completed documents.'),
                'href' => route('notary.requests.show', [$this->notaryRequest, 'tab' => 'audit']),
                'icon' => 'check-circle',
                'complete' => $isComplete,
                'active' => $hasRegisterEntry && ! $isComplete,
            ],
        ];
    }
}; ?>

<x-admin.page class="h-full flex-1 bg-slate-50/60 dark:bg-zinc-950" gap="gap-6" wide wire:key="notary-workflow-{{ $notaryRequest->id }}">
    @if (session('status'))
        <div class="flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-200">
            <flux:icon.check class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 space-y-2">
            <flux:button variant="ghost" size="sm" :href="route('notary.requests.show', $notaryRequest)" wire:navigate icon="arrow-left">
                {{ __('Case workspace') }}
            </flux:button>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-950 dark:text-white sm:text-3xl">{{ __('Notary Case Workflow') }}</h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $notaryRequest->title }}</p>
            </div>
        </div>
        <flux:button variant="primary" :href="route('notary.requests.show', $notaryRequest)" wire:navigate icon="squares-2x2">
            {{ __('Open full workspace') }}
        </flux:button>
    </div>

    <div class="grid gap-6 xl:grid-cols-12">
        <section class="xl:col-span-8">
            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800 sm:px-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">{{ __('Next workflow') }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-zinc-950 dark:text-white">{{ __('Continue this case step by step') }}</h2>
                </div>

                <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($this->workflowSteps as $index => $step)
                        <a
                            href="{{ $step['href'] }}"
                            wire:navigate
                            @class([
                                'group flex gap-4 px-5 py-5 transition sm:px-6',
                                'bg-blue-50/70 dark:bg-blue-950/20' => $step['active'],
                                'hover:bg-zinc-50 dark:hover:bg-zinc-800/40' => ! $step['active'],
                            ])
                        >
                            <span @class([
                                'flex size-10 shrink-0 items-center justify-center rounded-full border text-sm font-semibold',
                                'border-emerald-600 bg-emerald-600 text-white' => $step['complete'],
                                'border-blue-600 bg-blue-600 text-white shadow-sm shadow-blue-600/25' => $step['active'] && ! $step['complete'],
                                'border-zinc-200 bg-white text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400' => ! $step['complete'] && ! $step['active'],
                            ])>
                                @if ($step['complete'])
                                    <flux:icon.check class="size-5" />
                                @else
                                    {{ $index + 1 }}
                                @endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-zinc-950 dark:text-white">{{ $step['label'] }}</span>
                                    @if ($step['active'])
                                        <flux:badge size="sm" color="blue">{{ __('Do this now') }}</flux:badge>
                                    @elseif ($step['complete'])
                                        <flux:badge size="sm" color="emerald">{{ __('Done') }}</flux:badge>
                                    @endif
                                </span>
                                <span class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{ $step['description'] }}</span>
                            </span>
                            <flux:icon.arrow-right class="mt-2 size-5 shrink-0 text-zinc-400 transition group-hover:translate-x-0.5 group-hover:text-blue-600 dark:group-hover:text-blue-400" />
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <aside class="space-y-4 xl:col-span-4">
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">{{ __('Case snapshot') }}</p>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Status') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $notaryRequest->status->label() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Document') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $notaryRequest->documents->count() ?: ($notaryRequest->document_path ? 1 : 0) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Parties') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $notaryRequest->signers->count() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Sessions') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $notaryRequest->sessions->count() }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900 dark:border-blue-900/40 dark:bg-blue-950/20 dark:text-blue-100">
                <p class="font-semibold">{{ __('Tip') }}</p>
                <p class="mt-1 leading-relaxed">{{ __('This page is the guided workflow. The full workspace still has the detailed tabs and actions for each step.') }}</p>
            </div>
        </aside>
    </div>
</x-admin.page>
