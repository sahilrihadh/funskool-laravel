<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\ShippingMethodResource;
use App\Models\PaymentMethod;
use App\Models\ShippingMethod;

class CheckoutOptionsController extends Controller
{
    public function shippingMethods()
    {
        return ShippingMethodResource::collection(
            ShippingMethod::query()->where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    public function paymentMethods()
    {
        return PaymentMethodResource::collection(
            PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get()
        );
    }
}
