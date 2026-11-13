<?php

namespace App\Filament\Pages;

use App\Models\SalesChannel;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    Select::make('period')
                        ->options([
                            'today' => 'Today',
                            '7' => 'Last 7 days',
                            '30' => 'Last 30 days',
                            '90' => 'Last 90 days',
                            '365' => 'Last 12 months',
                        ])
                        ->default('30')
                        ->selectablePlaceholder(false),
                    Select::make('channel')
                        ->label('Sales channel')
                        ->placeholder('All channels')
                        ->options(fn () => ['storefront' => 'Own storefront / admin'] + SalesChannel::query()->pluck('name', 'id')->all()),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }
}
