<x-layouts.guest :title="__('Sign in')">
    <x-card
        :title="__('Sign in')"
        :description="__('Enter your credentials to continue.')"
    >
        <form
            method="POST"
            action="{{ url('/login') }}"
            class="mt-8 space-y-6"
        >
            @csrf

            <div class="space-y-2">
                <label
                    for="email"
                    class="text-primary-neutral block text-sm font-medium"
                >{{ __('Email') }}</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    class="surface-background-input ring-input text-primary-neutral focus:ring-primary shadow-xs w-full rounded-lg px-3 py-1.5 text-sm ring-1 focus:outline-none focus:ring-2"
                >
                @error('email')
                    <p class="text-danger text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-2">
                <label
                    for="password"
                    class="text-primary-neutral block text-sm font-medium"
                >{{ __('Password') }}</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="surface-background-input ring-input text-primary-neutral focus:ring-primary shadow-xs w-full rounded-lg px-3 py-1.5 text-sm ring-1 focus:outline-none focus:ring-2"
                >
                @error('password')
                    <p class="text-danger text-sm">{{ $message }}</p>
                @enderror
            </div>

            <label class="text-primary-neutral flex items-center gap-3 text-sm">
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                    class="accent-primary-active size-4 rounded"
                    @checked(old('remember'))
                >
                {{ __('Remember me') }}
            </label>

            <x-button>{{ __('Log in') }}</x-button>
        </form>
    </x-card>
</x-layouts.guest>
