<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Support\CsvExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Manual Order'),
        ];
    }

    /** Called by the table's "Download" action — exports exactly what the current filters/tabs show. */
    public function downloadCsv()
    {
        abort_unless(auth()->user()?->hasPermission('reports.view'), 403);

        return CsvExport::download('orders', $this->getFilteredSortedTableQuery()->with(['paymentMethod', 'shippingMethod', 'salesChannel']), [
            'Order #' => fn ($o) => $o->order_number,
            'External ref' => fn ($o) => $o->external_reference,
            'Channel' => fn ($o) => $o->salesChannel?->name ?? 'Storefront',
            'Placed at' => fn ($o) => $o->created_at->toDateTimeString(),
            'Status' => fn ($o) => OrderStatus::labelFor($o->status),
            'Payment status' => fn ($o) => PaymentStatus::labelFor($o->payment_status),
            'Payment method' => fn ($o) => $o->paymentMethod?->name,
            'Shipping method' => fn ($o) => $o->shippingMethod?->name,
            'Customer' => fn ($o) => $o->contactName(),
            // Contact details only for staff allowed to export customer data.
            'Email' => fn ($o) => auth()->user()->hasPermission('customers.export') ? $o->contactEmail() : '',
            'City' => fn ($o) => $o->shipping_address['city'] ?? '',
            'State' => fn ($o) => $o->shipping_address['state'] ?? '',
            'Subtotal' => fn ($o) => $o->subtotal,
            'Discount' => fn ($o) => $o->discount_amount,
            'Shipping' => fn ($o) => $o->shipping_amount,
            'Tax' => fn ($o) => $o->tax_amount,
            'Total' => fn ($o) => $o->total,
            'Refunded' => fn ($o) => $o->refunded_amount,
        ]);
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'new' => Tab::make('New')
                ->badge(fn () => OrderResource::getModel()::query()->where('status', OrderStatus::Pending->value)->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Pending->value)),
            'to_fulfil' => Tab::make('To Fulfil')
                ->badge(fn () => OrderResource::getModel()::query()->whereIn('status', OrderStatus::toFulfil())->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', OrderStatus::toFulfil())),
            'shipped' => Tab::make('Shipped')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Shipped->value)),
            'returns' => Tab::make('Returns')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [OrderStatus::ReturnRequested->value, OrderStatus::Returned->value])),
            'refund_due' => Tab::make('Refund Due')
                ->badge(fn () => OrderResource::getModel()::query()->where('status', OrderStatus::Cancelled->value)->whereIn('payment_status', PaymentStatus::captured())->whereColumn('refunded_amount', '<', 'total')->count() ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Cancelled->value)->whereIn('payment_status', PaymentStatus::captured())->whereColumn('refunded_amount', '<', 'total')),
            'on_hold' => Tab::make('On Hold')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::OnHold->value)),
            'cancelled' => Tab::make('Cancelled')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Cancelled->value)),
        ];
    }
}
