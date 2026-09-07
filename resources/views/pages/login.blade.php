<x-layouts.guest :title="__('Sign in')">
    <div class="elevation-0 border-secondary w-full max-w-sm rounded-xl border p-8">
        <h1 class="text-primary-neutral text-2xl font-semibold">Sign in</h1>
        <p class="text-secondary-neutral mt-1 text-sm">Enter your credentials to continue.</p>

        <form
            method="POST"
            action="{{ url('/login') }}"
            class="mt-6 space-y-4"
        >
            @csrf

            <div class="space-y-1">
                <label
                    for="email"
                    class="text-secondary-neutral block text-sm"
                >Email</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    class="surface-background-input border-secondary text-primary-neutral placeholder:text-placeholder focus:ring-primary w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2"
                >
            </div>

            <div class="space-y-1">
                <label
                    for="password"
                    class="text-secondary-neutral block text-sm"
                >Password</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="surface-background-input border-secondary text-primary-neutral placeholder:text-placeholder focus:ring-primary w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2"
                >
            </div>

            <label class="text-secondary-neutral flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                    @checked(old('remember'))
                >
                Remember me
            </label>

            @error('email')
                <p class="text-danger text-sm">{{ $message }}</p>
            @enderror
            @error('password')
                <p class="text-danger text-sm">{{ $message }}</p>
            @enderror

            <button
                type="submit"
                class="bg-primary-active hover:bg-primary-hover focus:ring-primary w-full rounded-lg px-4 py-2 font-medium text-white focus:outline-none focus:ring-2"
            >
                Log in
            </button>
        </form>
    </div>
</x-layouts.guest>
