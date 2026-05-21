<?php

use App\Models\NotaryCredential;
use App\Services\AttorneyApplicationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public NotaryCredential $credential;

    public string $rejectionReason = '';

    public function mount(NotaryCredential $credential): void
    {
        $this->authorize('view', $credential);
        $this->credential = $credential->load(['user.organization', 'reviewedBy']);
    }

    public function approve(AttorneyApplicationService $applications): void
    {
        $this->authorize('review', $this->credential);

        try {
            $applications->approve($this->credential, Auth::user());
            session()->flash('status', __('Attorney application approved.'));
            $this->redirect(route('admin.attorney-applications.index'), navigate: true);
        } catch (\RuntimeException $exception) {
            $this->addError('approve', $exception->getMessage());
        }
    }

    public function reject(AttorneyApplicationService $applications): void
    {
        $this->authorize('review', $this->credential);

        $this->validate([
            'rejectionReason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $applications->reject($this->credential, Auth::user(), $this->rejectionReason);
            session()->flash('status', __('Attorney application rejected.'));
            $this->redirect(route('admin.attorney-applications.index'), navigate: true);
        } catch (\RuntimeException $exception) {
            $this->addError('reject', $exception->getMessage());
        }
    }
}; ?>

<x-admin.page>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ __('Review attorney application') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $credential->user?->name }} · {{ $credential->user?->email }}</p>
        </div>
        <flux:button variant="ghost" :href="route('admin.attorney-applications.index')" wire:navigate>{{ __('Back to queue') }}</flux:button>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-sm font-bold uppercase tracking-wider text-zinc-500">{{ __('Application data') }}</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Status') }}</dt><dd class="font-medium capitalize">{{ str_replace('_', ' ', $credential->status) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Organization') }}</dt><dd class="font-medium">{{ $credential->user?->organization?->name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Commission') }}</dt><dd class="font-medium">{{ $credential->commission_number }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Jurisdiction') }}</dt><dd class="font-medium">{{ $credential->commission_jurisdiction }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Issued') }}</dt><dd class="font-medium">{{ $credential->commission_issued_at?->format('M j, Y') ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Expires') }}</dt><dd class="font-medium">{{ $credential->commission_expires_at?->format('M j, Y') ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Roll') }}</dt><dd class="font-medium">{{ $credential->roll_number ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('IBP') }}</dt><dd class="font-medium">{{ $credential->ibp_number ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('PTR') }}</dt><dd class="font-medium">{{ $credential->ptr_number ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('MCLE') }}</dt><dd class="font-medium">{{ $credential->mcle_compliance_number ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Submitted') }}</dt><dd class="font-medium">{{ $credential->submitted_at?->format('M j, Y g:i A') ?? '—' }}</dd></div>
                @if ($credential->reviewed_at)
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Reviewed') }}</dt><dd class="font-medium">{{ $credential->reviewed_at->format('M j, Y g:i A') }} · {{ $credential->reviewedBy?->name ?? '—' }}</dd></div>
                @endif
                @if ($credential->rejection_reason)
                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 dark:border-red-900/40 dark:bg-red-950/30">
                        <dt class="text-xs font-semibold text-red-800 dark:text-red-300">{{ __('Rejection reason') }}</dt>
                        <dd class="mt-1 text-red-900 dark:text-red-100">{{ $credential->rejection_reason }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-sm font-bold uppercase tracking-wider text-zinc-500">{{ __('Uploaded documents') }}</h2>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach ([
                    'commission' => __('Commission document'),
                    'ibp' => __('IBP ID'),
                    'ptr' => __('PTR'),
                    'mcle' => __('MCLE'),
                    'seal' => __('Seal image'),
                    'signature' => __('Signature'),
                ] as $key => $label)
                    @php
                        $path = match ($key) {
                            'commission' => $credential->commission_document_path,
                            'ibp' => $credential->ibp_document_path,
                            'ptr' => $credential->ptr_document_path,
                            'mcle' => $credential->mcle_document_path,
                            'seal' => $credential->seal_image_path,
                            'signature' => $credential->signature_image_path,
                            default => null,
                        };
                    @endphp
                    <li class="flex items-center justify-between gap-3 rounded-lg border border-zinc-100 px-3 py-2 dark:border-zinc-800">
                        <span class="text-zinc-700 dark:text-zinc-300">{{ $label }}</span>
                        @if ($path)
                            <a href="{{ route('admin.attorney-applications.document', ['credential' => $credential, 'document' => $key]) }}" target="_blank" class="text-teal-600 hover:underline dark:text-teal-400">{{ __('View') }}</a>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    @can('review', $credential)
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Decision') }}</h2>
            <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start">
                <flux:button variant="primary" type="button" wire:click="approve" wire:confirm="{{ __('Approve this attorney application? The user will receive notary access.') }}">
                    {{ __('Approve') }}
                </flux:button>
                <flux:error name="approve" />
            </div>
            <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <flux:field>
                    <flux:label>{{ __('Rejection reason') }}</flux:label>
                    <flux:textarea wire:model="rejectionReason" rows="3" placeholder="{{ __('Explain what is missing or invalid…') }}" />
                    <flux:error name="rejectionReason" />
                </flux:field>
                <flux:button variant="outline" type="button" class="mt-3" wire:click="reject" wire:confirm="{{ __('Reject this application? The applicant will remain or return to client access.') }}">
                    {{ __('Reject') }}
                </flux:button>
                <flux:error name="reject" />
            </div>
        </div>
    @endcan
</x-admin.page>
