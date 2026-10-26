<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Enums\ProposalStatus;
use App\Filament\Resources\Playbooks\PlaybookResource;
use App\Models\Playbook;
use App\Models\PlaybookProposal;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlaybookStats extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return auth()->user()->can(Permission::ManagePlaybooks);
    }

    protected function getStats(): array
    {
        return [
            Stat::make(__('Total playbooks'), Playbook::count())
                ->url(PlaybookResource::getUrl('index')),
            Stat::make(__('Unverified playbooks'), Playbook::query()->whereNull('verdict')->count())
                ->url(PlaybookResource::getUrl('index', ['filters' => ['verdict' => ['value' => 'unverified']]])),
            Stat::make(__('Pending proposals'), PlaybookProposal::query()->where('status', ProposalStatus::Pending)->count())
                ->url(PlaybookResource::getUrl('index', ['filters' => ['pending' => ['isActive' => true]]])),
        ];
    }
}
