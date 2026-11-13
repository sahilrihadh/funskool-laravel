<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnService
{
    public function __construct(
        protected OrderService $orders,
        protected PaymentService $payments,
        protected InventoryService $inventory,
    ) {}

    public function deliveredAt(Order $order): ?Carbon
    {
        if ($delivered = $order->shipments()->max('delivered_at')) {
            return Carbon::parse($delivered);
        }

        $deliveredAt = $order->statusHistories()->reorder()->where('status', OrderStatus::Delivered->value)->latest('id')->value('created_at');

        return $deliveredAt ? Carbon::parse($deliveredAt) : null;
    }

    public function returnDeadline(Order $order): ?Carbon
    {
        return $this->deliveredAt($order)?->addDays((int) Setting::get('return_window_days', 7))->endOfDay();
    }

    public function isEligible(Order $order): bool
    {
        $deadline = $this->returnDeadline($order);

        return $order->status === OrderStatus::Delivered->value && $deadline && $deadline->isFuture();
    }

    /** Quantity of each order item still returnable (ordered − already in an open/accepted return). */
    public function returnableQuantities(Order $order): array
    {
        $order->loadMissing('items', 'returnRequests');

        $requested = $order->returnRequests
            ->where('status', '!=', 'rejected')
            ->flatMap(fn (ReturnRequest $return) => $return->items)
            ->groupBy('order_item_id')
            ->map(fn ($lines) => $lines->sum('quantity'));

        return $order->items->mapWithKeys(fn ($item) => [
            $item->id => max(0, $item->quantity - (int) ($requested[$item->id] ?? 0)),
        ])->all();
    }

    /**
     * @param  array<int, int>  $quantities  order_item_id => quantity
     */
    public function request(Order $order, ?User $user, array $quantities, string $reason, ?string $note = null): ReturnRequest
    {
        if (! $this->isEligible($order)) {
            throw ValidationException::withMessages(['order' => 'This order is not eligible for a return (it must be delivered and within the '.Setting::get('return_window_days', 7).'-day return window).']);
        }

        if (! array_key_exists($reason, ReturnRequest::REASONS)) {
            throw ValidationException::withMessages(['reason' => 'Please choose a return reason.']);
        }

        $returnable = $this->returnableQuantities($order);
        $lines = [];

        foreach ($quantities as $itemId => $quantity) {
            $quantity = (int) $quantity;

            if ($quantity <= 0) {
                continue;
            }

            if (! isset($returnable[$itemId]) || $quantity > $returnable[$itemId]) {
                throw ValidationException::withMessages(['items' => 'You can not return more items than were ordered.']);
            }

            $lines[] = ['order_item_id' => (int) $itemId, 'quantity' => $quantity];
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['items' => 'Select at least one item to return.']);
        }

        return DB::transaction(function () use ($order, $user, $lines, $reason, $note) {
            $return = $order->returnRequests()->create([
                'user_id' => $user?->id,
                'status' => 'requested',
                'reason' => $reason,
                'items' => $lines,
                'customer_note' => $note,
            ]);

            $this->orders->updateStatus($order, OrderStatus::ReturnRequested->value, 'Return requested: '.ReturnRequest::REASONS[$reason]);

            return $return;
        });
    }

    public function approve(ReturnRequest $return, ?string $note = null): void
    {
        $return->update(['status' => 'approved', 'admin_note' => $note ?? $return->admin_note]);
    }

    public function reject(ReturnRequest $return, string $note): void
    {
        DB::transaction(function () use ($return, $note) {
            $return->update(['status' => 'rejected', 'admin_note' => $note, 'resolved_at' => now()]);

            $order = $return->order;

            if ($order->status === OrderStatus::ReturnRequested->value && ! $order->returnRequests()->whereIn('status', ['requested', 'approved', 'received'])->exists()) {
                $this->orders->updateStatus($order, OrderStatus::Delivered->value, "Return rejected: {$note}");
            }
        });
    }

    /** Parcel is back in the warehouse; optionally put items back into sellable stock. */
    public function receive(ReturnRequest $return, bool $restock, ?string $note = null): void
    {
        DB::transaction(function () use ($return, $restock, $note) {
            $order = $return->order->loadMissing('items.product', 'items.variant');

            if ($restock && ! $return->restocked) {
                $items = $order->items->keyBy('id');

                foreach ($return->items as $line) {
                    $item = $items->get($line['order_item_id']);

                    if ($item?->product) {
                        $this->inventory->add($item->product, $item->variant, (int) $line['quantity'], 'return', $return);
                    }
                }
            }

            $return->update([
                'status' => 'received',
                'restocked' => $return->restocked || $restock,
                'admin_note' => $note ?? $return->admin_note,
            ]);

            if ($order->status === OrderStatus::ReturnRequested->value) {
                $this->orders->updateStatus($order, OrderStatus::Returned->value, 'Return received'.($restock ? ' and restocked.' : '.'));
            }
        });
    }

    public function refund(ReturnRequest $return, float $amount, bool $viaGateway = true): void
    {
        $order = $return->order;

        if ($order->isPaid()) {
            $this->payments->refund($order, $amount, "Return #{$return->id}: ".(ReturnRequest::REASONS[$return->reason] ?? $return->reason), $viaGateway);
        }

        $return->update(['status' => 'refunded', 'refund_amount' => $amount, 'resolved_at' => now()]);
    }
}
