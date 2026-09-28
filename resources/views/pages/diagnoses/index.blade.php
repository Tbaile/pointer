<?php

use App\Models\Diagnosis;
use Illuminate\Contracts\Pagination\Paginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] #[Title('Diagnoses')] class extends Component {
    use WithPagination;

    /**
     * @return Paginator<int, Diagnosis>
     */
    #[Computed]
    public function diagnoses(): Paginator
    {
        return Diagnosis::with('tags')->latest()->simplePaginate(10);
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-6 px-6 py-10">
    <h1 class="text-primary-neutral text-3xl font-semibold">{{ __('Diagnoses') }}</h1>

    <table class="w-full text-left text-sm">
        <thead class="text-secondary-neutral border-secondary border-b">
            <tr>
                <th class="py-3 pr-4 font-medium">{{ __('Tags') }}</th>
                <th class="py-3 pr-4 font-medium">{{ __('System type') }}</th>
                <th class="py-3 pr-4 font-medium">{{ __('Verdict') }}</th>
                <th class="py-3 font-medium">{{ __('Created') }}</th>
            </tr>
        </thead>
        <tbody class="text-primary-neutral">
            @forelse ($this->diagnoses as $diagnosis)
                <tr
                    wire:key="{{ $diagnosis->id }}"
                    class="border-secondary border-b"
                >
                    <td class="py-3 pr-4">
                        <a
                            href="{{ route('diagnoses.edit', $diagnosis) }}"
                            class="hover:underline"
                        >{{ $diagnosis->tags->pluck('tag')->join(', ') }}</a>
                    </td>
                    <td class="py-3 pr-4">{{ $diagnosis->system_type->value }}</td>
                    <td class="py-3 pr-4">{{ $diagnosis->verdict?->value ?? __('Not verified') }}</td>
                    <td class="py-3">{{ $diagnosis->created_at->format('Y-m-d H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td
                        colspan="4"
                        class="text-secondary-neutral py-6 text-center"
                    >
                        {{ __('No diagnoses yet.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $this->diagnoses->links() }}
</div>
