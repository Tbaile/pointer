<x-layouts.guest :title="__('Sign in')">
    <div class="w-full max-w-sm elevation-0 border border-secondary rounded-xl p-8">
        <h1 class="text-2xl font-semibold text-primary-neutral">Sign in</h1>
        <p class="mt-1 text-sm text-secondary-neutral">Enter your credentials to continue.</p>

        <form method="POST" action="{{ url('/login') }}" class="mt-6 space-y-4">
            @csrf

            <div class="space-y-1">
                <label for="email" class="block text-sm text-secondary-neutral">Email</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    class="w-full rounded-lg surface-background-input border border-secondary px-3 py-2 text-primary-neutral placeholder:text-placeholder focus:outline-none focus:ring-2 focus:ring-primary"
                >
            </div>

            <div class="space-y-1">
                <label for="password" class="block text-sm text-secondary-neutral">Password</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-lg surface-background-input border border-secondary px-3 py-2 text-primary-neutral placeholder:text-placeholder focus:outline-none focus:ring-2 focus:ring-primary"
                >
            </div>

            <label class="flex items-center gap-2 text-sm text-secondary-neutral">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                Remember me
            </label>

            @error('email')
                <p class="text-sm text-danger">{{ $message }}</p>
            @enderror
            @error('password')
                <p class="text-sm text-danger">{{ $message }}</p>
            @enderror

            <button
                type="submit"
                class="w-full rounded-lg bg-primary-active px-4 py-2 font-medium text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary"
            >
                Log in
            </button>
        </form>
    </div>
</x-layouts.guest>
