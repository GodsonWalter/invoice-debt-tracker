@can('delete-own-account')
<section class="space-y-6">
    <form method="post" action="{{ route('profile.destroy') }}" class="p-6" data-delete-confirm
        data-delete-confirm-message="Your account will be deactivated and signed out. Platform support may restore it later.">
        @csrf
        @method('delete')

        <header>
            <h2 class="text-lg font-medium text-gray-900">
                {{ __('Delete Account') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('Your account will be deactivated and soft deleted. You will be signed out and must contact platform support if it needs to be restored. Workspaces you own must be transferred before deletion.') }}
            </p>
        </header>

        <div class="mt-6">
            <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

            <x-text-input
                id="password"
                name="password"
                type="password"
                class="mt-1 block w-3/4"
                placeholder="{{ __('Password') }}"
            />

            <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            <x-input-error :messages="$errors->userDeletion->get('account')" class="mt-2" />
        </div>

        <div class="mt-6 flex justify-end">
            <x-danger-button>
                {{ __('Delete Account') }}
            </x-danger-button>
        </div>
    </form>
</section>
@endcan
