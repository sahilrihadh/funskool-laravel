<?php

namespace App\Filament\Resources\ReturnRequests\Pages;

use App\Filament\Resources\ReturnRequests\ReturnRequestResource;
use App\Models\ReturnRequest;
use App\Services\ReturnService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditReturnRequest extends EditRecord
{
    protected static string $resource = ReturnRequestResource::class;

    protected function returnRequest(): ReturnRequest
    {
        return $this->getRecord();
    }

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
        $this->fillForm();
        Notification::make()->title($success)->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->color('success')
                ->icon('heroicon-o-check')
                ->visible(fn () => $this->returnRequest()->status === 'requested')
                ->schema([Textarea::make('note')->label('Instructions for the customer')->rows(3)])
                ->action(fn (array $data, ReturnService $returns) => $this->attempt(fn () => $returns->approve($this->returnRequest(), $data['note'] ?? null), 'Return approved')),

            Action::make('reject')
                ->color('danger')
                ->icon('heroicon-o-x-mark')
                ->visible(fn () => in_array($this->returnRequest()->status, ['requested', 'approved'], true))
                ->schema([Textarea::make('note')->label('Reason (shown to the customer)')->required()->rows(3)])
                ->action(fn (array $data, ReturnService $returns) => $this->attempt(fn () => $returns->reject($this->returnRequest(), $data['note']), 'Return rejected')),

            Action::make('receive')
                ->label('Mark Received')
                ->icon('heroicon-o-inbox-arrow-down')
                ->visible(fn () => in_array($this->returnRequest()->status, ['requested', 'approved'], true))
                ->schema([
                    Toggle::make('restock')->label('Item(s) are resellable — put back into stock')->default(true),
                    Textarea::make('note')->label('Inspection notes')->rows(2),
                ])
                ->action(fn (array $data, ReturnService $returns) => $this->attempt(fn () => $returns->receive($this->returnRequest(), (bool) $data['restock'], $data['note'] ?? null), 'Return received')),

            Action::make('refund')
                ->label('Refund')
                ->color('warning')
                ->icon('heroicon-o-receipt-refund')
                ->visible(fn () => auth()->user()?->hasPermission('orders.refund') && in_array($this->returnRequest()->status, ['approved', 'received'], true))
                ->schema([
                    TextInput::make('amount')
                        ->numeric()
                        ->required()
                        ->default(fn () => min($this->returnRequest()->itemsValue(), $this->returnRequest()->order->refundableAmount()))
                        ->helperText(fn () => 'Returned items are worth '.number_format($this->returnRequest()->itemsValue(), 2).'. Unpaid (e.g. COD not collected) orders are closed without a payment refund.'),
                    Toggle::make('via_gateway')->label('Refund through the payment gateway')->default(true),
                ])
                ->requiresConfirmation()
                ->action(fn (array $data, ReturnService $returns) => $this->attempt(fn () => $returns->refund($this->returnRequest(), (float) $data['amount'], (bool) $data['via_gateway']), 'Refund issued')),
        ];
    }
}
