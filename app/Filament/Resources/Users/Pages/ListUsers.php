<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Support\CsvExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** Called by the table's "Download" action. Personal data — needs customers.export. */
    public function downloadCsv()
    {
        abort_unless(auth()->user()?->hasPermission('customers.export'), 403);

        return CsvExport::download('users', $this->getFilteredSortedTableQuery()->withCount('orders'), [
            'Name' => fn ($u) => $u->name,
            'Email' => fn ($u) => $u->email,
            'Phone' => fn ($u) => $u->phone,
            'Role' => fn ($u) => UserRole::tryFrom($u->role)?->getLabel() ?? $u->role,
            'Marketing consent' => fn ($u) => $u->marketing_opt_in ? 'yes ('.$u->marketing_opt_in_at?->toDateString().')' : 'no',
            'Orders' => fn ($u) => $u->orders_count,
            'Joined' => fn ($u) => $u->created_at?->toDateString(),
        ]);
    }

    public function getTabs(): array
    {
        return [
            'customers' => Tab::make('Customers')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', 'customer')),
            'staff' => Tab::make('Staff')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', '!=', 'customer'))
                ->visible(fn () => auth()->user()?->hasPermission('staff.manage')),
            'all' => Tab::make('All'),
        ];
    }
}
