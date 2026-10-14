<?php

namespace App\Filament\Resources\Playbooks\Schemas;

use App\Models\Playbook;
use Closure;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PlaybookForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TagsInput::make('tags')
                    ->required()
                    ->splitKeys([',', 'Tab'])
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (! is_array($value) || $value === [] || count($value) > 10) {
                            $fail(__('Enter between 1 and 10 tags.'));

                            return;
                        }

                        foreach ($value as $tag) {
                            if (! is_string($tag) || strlen($tag) > 50 || ! preg_match(Playbook::TAG_PATTERN, $tag)) {
                                $fail(__('Tags must be lowercase kebab-case, e.g. disk-full.'));
                            }
                        }
                    }),
                MarkdownEditor::make('body')
                    ->required()
                    ->helperText(__('Saving marks the playbook as confirmed by you.')),
            ]);
    }
}
