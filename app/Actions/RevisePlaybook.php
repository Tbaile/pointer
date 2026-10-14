<?php

namespace App\Actions;

use App\Enums\PlaybookVerdict;
use App\Models\Playbook;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RevisePlaybook
{
    /**
     * Replace the content and tags, confirming the playbook as the reviewer.
     *
     * @param  list<string>  $tags
     */
    public function handle(Playbook $playbook, User $reviewer, string $title, string $body, array $tags): void
    {
        DB::transaction(function () use ($playbook, $reviewer, $title, $body, $tags): void {
            $playbook
                ->forceFill([
                    'title' => $title,
                    'body' => $body,
                    'verdict' => PlaybookVerdict::Confirmed,
                    'verified_by' => $reviewer->id,
                    'verified_at' => now(),
                ])
                ->save();

            $playbook->tags()->sync(array_map(fn (string $name): int => Tag::firstOrCreate(['name' => $name])->id, array_unique($tags)));
            $playbook->unsetRelation('tags');
        });
    }
}
