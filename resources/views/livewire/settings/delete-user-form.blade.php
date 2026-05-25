<?php

use App\Livewire\Actions\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $password = '';

    public function deleteUser(Logout $logout, Request $request): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $logout($request);

        $user->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
            {{ __('Delete account') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form wire:submit="deleteUser" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete your account?') }}</flux:heading>
                <flux:subheading class="mt-2">
                    {{ __('Enter your password to confirm. All resources and data will be permanently removed.') }}
                </flux:subheading>
            </div>

            <flux:input wire:model="password" label="{{ __('Password') }}" type="password" name="password" autocomplete="current-password" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" type="submit">{{ __('Delete account') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
