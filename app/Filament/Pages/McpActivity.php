<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class McpActivity extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $slug = 'mcp-activity';

    public static function canAccess(): bool
    {
        return auth()->user()->can(Permission::ViewMcpActivity);
    }

    public static function getNavigationLabel(): string
    {
        return __('MCP activity');
    }

    public function getTitle(): string
    {
        return __('MCP activity');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search, array $filters, int $page, int $recordsPerPage): LengthAwarePaginator {
                $entries = $this->entries()
                    ->when(filled($search), fn (Collection $entries): Collection => $entries->filter(
                        fn (array $entry): bool => Str::contains(json_encode($entry, JSON_THROW_ON_ERROR), $search, ignoreCase: true),
                    ));

                foreach (['user_email', 'client', 'tool', 'sos_id'] as $key) {
                    $value = $filters[$key]['value'] ?? null;

                    if (filled($value)) {
                        $entries = $entries->where($key, $value);
                    }
                }

                return new LengthAwarePaginator(
                    $entries->forPage($page, $recordsPerPage),
                    total: $entries->count(),
                    perPage: $recordsPerPage,
                    currentPage: $page,
                );
            })
            ->columns([
                TextColumn::make('datetime')
                    ->label(__('Time'))
                    ->dateTime(),
                TextColumn::make('user_email')
                    ->label(__('User')),
                TextColumn::make('client')
                    ->label(__('Client')),
                TextColumn::make('tool')
                    ->label(__('Tool')),
                TextColumn::make('sos_id')
                    ->label(__('SOS id')),
                IconColumn::make('success')
                    ->label(__('Success'))
                    ->boolean(),
                TextColumn::make('duration_ms')
                    ->label(__('Duration'))
                    ->suffix(' ms'),
            ])
            ->filters([
                SelectFilter::make('user_email')
                    ->label(__('User'))
                    ->options(fn (): array => $this->options('user_email')),
                SelectFilter::make('client')
                    ->label(__('Client'))
                    ->options(fn (): array => $this->options('client')),
                SelectFilter::make('tool')
                    ->label(__('Tool'))
                    ->options(fn (): array => $this->options('tool')),
                SelectFilter::make('sos_id')
                    ->label(__('SOS id'))
                    ->options(fn (): array => $this->options('sos_id')),
            ])
            ->recordActions([
                Action::make('arguments')
                    ->label(__('Arguments'))
                    ->modalSubmitAction(false)
                    ->modalContent(fn (array $record): HtmlString => new HtmlString(
                        '<pre class="text-sm whitespace-pre-wrap">'.e(json_encode($record['arguments'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)).'</pre>',
                    )),
            ])
            ->searchable()
            ->poll('5s');
    }

    /**
     * Every logged tool call, newest first, keyed by file and line.
     *
     * @return Collection<string, array<string, mixed>>
     */
    private function entries(): Collection
    {
        return once(function (): Collection {
            $entries = collect();
            $files = glob(Str::replaceLast('.log', '-*.log', config('logging.channels.mcp.path'))) ?: [];

            rsort($files);

            foreach ($files as $file) {
                $lines = array_reverse(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], preserve_keys: true);

                foreach ($lines as $number => $line) {
                    $line = json_decode($line, true);

                    $entries->put(basename($file).':'.$number, [
                        ...$line['context'],
                        'datetime' => $line['datetime'],
                        'sos_id' => $line['context']['arguments']['sos_id'] ?? null,
                    ]);
                }
            }

            return $entries;
        });
    }

    /**
     * @return array<string, string>
     */
    private function options(string $key): array
    {
        return $this->entries()->pluck($key)->filter()->unique()->sort()->mapWithKeys(fn (string $value): array => [$value => $value])->all();
    }
}
