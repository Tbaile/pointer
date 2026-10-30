<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class McpSetup extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static ?string $slug = 'mcp-setup';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('MCP setup');
    }

    public function getTitle(): string
    {
        return __('MCP setup');
    }

    public function content(Schema $schema): Schema
    {
        $url = url('/mcp/pointer');

        return $schema
            ->components([
                Tabs::make('Clients')
                    ->tabs([
                        Tab::make('Claude Code')
                            ->schema([
                                Text::make(__('Run this in a terminal, then run /mcp inside Claude Code and select pointer to sign in.')),
                                $this->snippet('claudeCode', "claude mcp add --transport http --scope user pointer {$url}", 'shell'),
                            ]),
                        Tab::make('Claude')
                            ->schema([
                                Text::make(__('In Claude, go to Customize > Connectors, click Add custom connector, paste this URL and click Add. If asked for an OAuth client, choose Register automatically. Then click Connect to sign in. On Team and Enterprise plans an Owner adds the connector from Organization settings > Connectors, and members only click Connect.')),
                                $this->snippet('claudeDesktop', $url, 'text'),
                                Actions::make([
                                    Action::make('openClaudeConnectors')
                                        ->label(__('Open Claude connectors'))
                                        ->url('https://claude.ai/customize/connectors', shouldOpenInNewTab: true),
                                ]),
                            ]),
                        Tab::make('ChatGPT')
                            ->schema([
                                Text::make(__('In ChatGPT, go to Plugins, click +, then Add custom MCP server. Name it pointer, paste this URL as Server URL, choose OAuth and confirm the warning, then click Create as a plugin. Install it from your plugins and sign in, then type @pointer in a chat to use it. On Business, Enterprise and Edu workspaces you need the permission to create plugins with MCPs.')),
                                $this->snippet('chatGpt', $url, 'text'),
                                Actions::make([
                                    Action::make('openChatGptPlugins')
                                        ->label(__('Open ChatGPT plugins'))
                                        ->url('https://chatgpt.com/plugins', shouldOpenInNewTab: true),
                                ]),
                            ]),
                        Tab::make('Codex CLI')
                            ->schema([
                                Text::make(__('Add the server, then sign in.')),
                                $this->snippet('codexCli', "codex mcp add pointer --url {$url}\ncodex mcp login pointer", 'shell'),
                                Text::make(__('Or add it to ~/.codex/config.toml and run codex mcp login pointer.')),
                                $this->snippet('codexConfig', "[mcp_servers.pointer]\nurl = \"{$url}\"", 'toml'),
                            ]),
                        Tab::make('Cursor')
                            ->schema([
                                Text::make(__('Install it with one click, or add it to ~/.cursor/mcp.json. Cursor asks you to sign in the first time it connects.')),
                                Actions::make([
                                    Action::make('installCursor')
                                        ->label(__('Add to Cursor'))
                                        ->url('cursor://anysphere.cursor-deeplink/mcp/install?name=pointer&config='.rawurlencode(base64_encode($this->json(['url' => $url])))),
                                ]),
                                $this->snippet('cursorConfig', $this->json(['mcpServers' => ['pointer' => ['url' => $url]]], pretty: true), 'json'),
                            ]),
                        Tab::make('VS Code')
                            ->schema([
                                Text::make(__('Install it with one click, or add it to .vscode/mcp.json. VS Code asks you to sign in the first time it connects.')),
                                Actions::make([
                                    Action::make('installVsCode')
                                        ->label(__('Add to VS Code'))
                                        ->url('vscode:mcp/install?'.rawurlencode($this->json(['name' => 'pointer', 'type' => 'http', 'url' => $url]))),
                                ]),
                                $this->snippet('vsCodeConfig', $this->json(['servers' => ['pointer' => ['type' => 'http', 'url' => $url]]], pretty: true), 'json'),
                            ]),
                    ]),
                Section::make(__('Prompt'))
                    ->description(__('Pointer serves this prompt as Diagnose Machine, it tells the agent how to run the investigation. Some clients, like ChatGPT, can\'t load prompts from an MCP server: if yours doesn\'t offer Diagnose Machine, paste this as your first message, followed by the SOS id and the problem.'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        $this->snippet('prompt', view('prompts.mcp.pointer')->render(), 'text'),
                    ]),
            ]);
    }

    private function snippet(string $name, string $code, string $language): TextEntry
    {
        return TextEntry::make($name)
            ->hiddenLabel()
            ->state("```{$language}\n{$code}\n```")
            ->markdown()
            ->copyable()
            ->copyableState($code);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function json(array $data, bool $pretty = false): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | ($pretty ? JSON_PRETTY_PRINT : 0));
    }
}
