<?php

namespace App\Filament\Resources\SupportTickets\Widgets;

use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TicketStatsWidget extends BaseWidget
{
    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $total = SupportTicket::query()->count();
        $pending = SupportTicket::query()->whereIn('status', TicketStatus::pending())->count();
        $solved = SupportTicket::query()->whereIn('status', TicketStatus::resolved())->count();
        $firstResponse = SupportTicket::query()->whereNotNull('first_response_at')->where('created_at', '>=', now()->subDays(30))->get()
            ->avg(fn (SupportTicket $ticket) => $ticket->created_at->diffInMinutes($ticket->first_response_at));

        return [
            Stat::make('Total tickets', number_format($total))
                ->icon('heroicon-o-ticket'),
            Stat::make('Pending tickets', number_format($pending))
                ->description($firstResponse ? 'Avg. first reply '.($firstResponse >= 60 ? round($firstResponse / 60, 1).' h' : round($firstResponse).' min').' (30 days)' : null)
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Solved tickets', number_format($solved))
                ->icon('heroicon-o-check-circle')
                ->color('success'),
        ];
    }
}
