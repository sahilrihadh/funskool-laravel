<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Phone / offline orders — priced and stock-checked exactly like online checkout. */
class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $lines = collect($data['items'] ?? [])->map(fn (array $item) => [
            'product' => Product::query()->findOrFail($item['product_id']),
            'variant' => ! empty($item['product_variant_id']) ? ProductVariant::query()->find($item['product_variant_id']) : null,
            'quantity' => max(1, (int) $item['quantity']),
        ])->all();

        try {
            return app(OrderService::class)->placeOrder($lines, [
                'user' => User::query()->findOrFail($data['user_id']),
                'shipping_method_id' => $data['shipping_method_id'] ?? null,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'coupon_code' => $data['coupon_code'] ?? null,
                'shipping_address' => $data['shipping_address'],
                'admin_note' => $data['admin_note'] ?? null,
            ]);
        } catch (ValidationException $e) {
            Notification::make()->title('Order not created')->body(collect($e->errors())->flatten()->implode(' '))->danger()->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
