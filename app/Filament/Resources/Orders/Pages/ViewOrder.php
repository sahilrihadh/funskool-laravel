<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Http\Controllers\Storefront\PaymentController;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ShippingService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;
use Throwable;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function order(): Order
    {
        return $this->getRecord();
    }

    protected function can(string $ability): bool
    {
        return (bool) auth()->user()?->hasPermission($ability);
    }

    /** Runs an operation and reports domain errors as a notification instead of a 500. */
    protected function attempt(callable $operation, string $success): void
    {
        try {
            $operation();
        } catch (ValidationException $e) {
            Notification::make()->title('Not possible')->body(collect($e->errors())->flatten()->implode(' '))->danger()->send();

            return;
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Something went wrong')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->record->refresh();

        Notification::make()->title($success)->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createShipment')
                ->label('Ship')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->visible(fn () => $this->can('orders.manage') && $this->order()->statusEnum()?->canTransitionTo(OrderStatus::Shipped) && $this->order()->status !== OrderStatus::Shipped->value)
                ->schema([
                    Select::make('carrier')
                        ->options(fn () => app(ShippingService::class)->availableCarriers())
                        ->default('manual')
                        ->live()
                        ->required(),
                    TextInput::make('courier_name')
                        ->label('Courier')
                        ->placeholder('e.g. Delhivery, Blue Dart, India Post')
                        ->visible(fn (Get $get) => $get('carrier') === 'manual')
                        ->required(fn (Get $get) => $get('carrier') === 'manual'),
                    TextInput::make('tracking_number')
                        ->visible(fn (Get $get) => $get('carrier') === 'manual'),
                    TextInput::make('tracking_url')
                        ->url()
                        ->visible(fn (Get $get) => $get('carrier') === 'manual'),
                ])
                ->action(fn (array $data, ShippingService $shipping) => $this->attempt(
                    fn () => $data['carrier'] === 'shiprocket'
                        ? $shipping->createViaShiprocket($this->order())
                        : $shipping->createManual($this->order(), $data['courier_name'], $data['tracking_number'] ?? null, $data['tracking_url'] ?? null),
                    'Order shipped — the customer has been notified',
                )),

            Action::make('markDelivered')
                ->label('Mark Delivered')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->can('orders.manage') && $this->order()->status === OrderStatus::Shipped->value)
                ->action(fn (OrderService $orders) => $this->attempt(
                    fn () => $orders->updateStatus($this->order(), OrderStatus::Delivered->value, 'Marked delivered by staff'),
                    'Order marked as delivered',
                )),

            Action::make('recordPayment')
                ->label('Record Payment')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $this->can('orders.manage') && ! $this->order()->isPaid() && $this->order()->status !== OrderStatus::Cancelled->value)
                ->schema([
                    TextInput::make('amount')->numeric()->required()->default(fn () => (float) $this->order()->total),
                    TextInput::make('reference')->label('Reference (UTR / receipt no.)')->maxLength(255),
                    Textarea::make('note')->rows(2),
                ])
                ->action(fn (array $data, PaymentService $payments) => $this->attempt(
                    fn () => $payments->recordManualPayment($this->order(), (float) $data['amount'], $data['reference'] ?? null, $data['note'] ?? null),
                    'Payment recorded',
                )),

            ActionGroup::make([
                Action::make('updateStatus')
                    ->label('Change Status')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn () => $this->can('orders.manage') && $this->order()->statusEnum()?->allowedTransitions() !== [])
                    ->schema([
                        Select::make('status')
                            ->options(fn () => collect($this->order()->statusEnum()?->allowedTransitions() ?? [])
                                ->reject(fn (OrderStatus $status) => $status === OrderStatus::Cancelled)
                                ->mapWithKeys(fn (OrderStatus $status) => [$status->value => $status->getLabel()]))
                            ->helperText('Only valid next steps are listed. Use "Ship" for shipping and "Cancel Order" to cancel.')
                            ->required(),
                        Textarea::make('note')->label('Note (shown on the timeline)')->rows(2),
                    ])
                    ->action(fn (array $data, OrderService $orders) => $this->attempt(
                        fn () => $orders->updateStatus($this->order(), $data['status'], $data['note'] ?? null),
                        'Order status updated',
                    )),

                Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-receipt-refund')
                    ->color('warning')
                    ->visible(fn () => $this->can('orders.refund') && $this->order()->isPaid() && $this->order()->refundableAmount() > 0)
                    ->schema([
                        TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->default(fn () => $this->order()->refundableAmount())
                            ->helperText(fn () => 'Up to '.number_format($this->order()->refundableAmount(), 2).' can be refunded.'),
                        Textarea::make('reason')->required()->rows(2),
                        Toggle::make('via_gateway')
                            ->label('Refund through the payment gateway')
                            ->helperText('Turn off for COD / bank-transfer orders refunded outside the system.')
                            ->default(fn () => $this->order()->transactions()->where('type', 'payment')->where('status', 'succeeded')->whereIn('gateway', array_keys(PaymentService::GATEWAYS))->exists()),
                    ])
                    ->requiresConfirmation()
                    ->modalDescription('Refunds sent to a gateway cannot be undone.')
                    ->action(fn (array $data, PaymentService $payments) => $this->attempt(
                        fn () => $payments->refund($this->order(), (float) $data['amount'], $data['reason'], (bool) $data['via_gateway']),
                        'Refund issued',
                    )),

                Action::make('paymentStatus')
                    ->label('Set Payment Status')
                    ->icon('heroicon-o-credit-card')
                    ->visible(fn () => $this->can('orders.refund'))
                    ->schema([
                        Select::make('payment_status')
                            ->options(collect(PaymentStatus::options())->only(['pending', 'authorized', 'failed', 'disputed']))
                            ->helperText('For exceptions such as chargebacks. Payments and refunds should use their own actions.')
                            ->required(),
                        Textarea::make('reason')->required()->rows(2),
                    ])
                    ->action(function (array $data): void {
                        $this->attempt(function () use ($data) {
                            $order = $this->order();
                            $from = $order->payment_status;
                            $order->update(['payment_status' => $data['payment_status']]);
                            $order->statusHistories()->create([
                                'status' => $order->status,
                                'user_id' => auth()->id(),
                                'note' => "Payment status {$from} → {$data['payment_status']}: {$data['reason']}",
                                'is_customer_visible' => false,
                            ]);
                            ActivityLog::record('payment_status', $order, ['from' => $from, 'to' => $data['payment_status'], 'reason' => $data['reason']]);
                        }, 'Payment status updated');
                    }),

                Action::make('paymentLink')
                    ->label('Copy Payment Link')
                    ->icon('heroicon-o-link')
                    ->visible(fn () => app(PaymentService::class)->needsOnlinePayment($this->order()))
                    ->action(fn () => Notification::make()
                        ->title('Payment link (valid 48 hours)')
                        ->body(PaymentController::startUrl($this->order()))
                        ->persistent()
                        ->send()),

                Action::make('cancel')
                    ->label('Cancel Order')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn () => $this->can('orders.manage') && $this->order()->statusEnum()?->canTransitionTo(OrderStatus::Cancelled) && $this->order()->status !== OrderStatus::Cancelled->value)
                    ->schema([Textarea::make('reason')->required()->rows(2)])
                    ->requiresConfirmation()
                    ->modalDescription('Stock is returned to inventory. Paid orders must then be refunded with the Refund action.')
                    ->action(fn (array $data, OrderService $orders) => $this->attempt(
                        fn () => $orders->cancel($this->order(), $data['reason']),
                        'Order cancelled and stock restored',
                    )),
            ])->label('More')->icon('heroicon-o-ellipsis-vertical')->button(),

            ActionGroup::make([
                Action::make('invoice')
                    ->label('Invoice')
                    ->icon('heroicon-o-document-text')
                    ->url(fn () => route('orders.invoice', $this->order()), shouldOpenInNewTab: true),
                Action::make('packingSlip')
                    ->label('Packing Slip')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->url(fn () => route('orders.invoice', [$this->order(), 'type' => 'packing-slip']), shouldOpenInNewTab: true),
            ])->label('Print')->icon('heroicon-o-printer')->button()->color('gray'),

            EditAction::make()->label('Edit address / note'),
        ];
    }
}
