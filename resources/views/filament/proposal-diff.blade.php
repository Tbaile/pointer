<div class="space-y-3">
    @if ($proposal->tags !== null)
        <p class="text-secondary-neutral text-sm">
            {{ __('Tags: :from → :to', ['from' => implode(', ', $proposal->playbook->tagNames()), 'to' => implode(', ', $proposal->tags)]) }}
        </p>
    @endif

    <x-diff
        :from="$proposal->playbook->body"
        :to="$proposal->body"
    />
</div>
