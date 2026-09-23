<x-layouts.guest :title="__('Authorize application')">
    <x-card
        :title="__('Authorize :name', ['name' => $client->name])"
        :description="__('This application is requesting access to your account.')"
        x-data="{ submitting: false }"
    >
        <div class="border-secondary mt-6 rounded-lg border p-4">
            <p class="text-tertiary-neutral text-sm">{{ __('Signed in as') }}</p>
            <p class="text-primary-neutral font-medium">{{ $user->email }}</p>
        </div>

        @if (count($scopes) > 0)
            <div class="mt-4 space-y-2">
                <p class="text-primary-neutral text-sm font-medium">{{ __('This application will be able to:') }}</p>

                <ul class="text-secondary-neutral list-inside list-disc space-y-1 text-sm">
                    @foreach ($scopes as $scope)
                        <li>{{ $scope->description }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-6 flex gap-3">
            <form
                method="POST"
                action="{{ route('passport.authorizations.deny') }}"
                x-on:submit="submitting = true; setTimeout(() => window.close(), 5000)"
                class="flex-1"
            >
                @csrf
                @method('DELETE')
                <input
                    type="hidden"
                    name="state"
                    value=""
                >
                <input
                    type="hidden"
                    name="client_id"
                    value="{{ $client->getKey() }}"
                >
                <input
                    type="hidden"
                    name="auth_token"
                    value="{{ $authToken }}"
                >

                <x-button
                    variant="secondary"
                    x-bind:disabled="submitting"
                >{{ __('Cancel') }}</x-button>
            </form>

            <form
                method="POST"
                action="{{ route('passport.authorizations.approve') }}"
                x-on:submit="submitting = true; setTimeout(() => window.close(), 5000)"
                class="flex-1"
            >
                @csrf
                <input
                    type="hidden"
                    name="state"
                    value=""
                >
                <input
                    type="hidden"
                    name="client_id"
                    value="{{ $client->getKey() }}"
                >
                <input
                    type="hidden"
                    name="auth_token"
                    value="{{ $authToken }}"
                >

                <x-button x-bind:disabled="submitting">
                    <span x-show="! submitting">{{ __('Authorize') }}</span>
                    <span
                        x-show="submitting"
                        x-cloak
                    >{{ __('Authorizing...') }}</span>
                </x-button>
            </form>
        </div>
    </x-card>
</x-layouts.guest>
