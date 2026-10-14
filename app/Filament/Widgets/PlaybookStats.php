<?php

namespace App\Filament\Widgets;

use App\Enums\ProposalStatus;
use App\Filament\Resources\Playbooks\PlaybookResource;
use App\Models\Playbook;
use App\Models\PlaybookProposal;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlaybookStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make(__('Unverified playbooks'), Playbook::whereNull('verdict')->count())
                ->url(PlaybookResource::getUrl('index', ['filters' => ['verdict' => ['value' => 'unverified']]])),
            Stat::make(__('Pending proposals'), PlaybookProposal::query()->where('status', ProposalStatus::Pending)->count())
                ->url(PlaybookResource::getUrl('index', ['filters' => ['pending' => ['isActive' => true]]])),
        ];
    }
}
