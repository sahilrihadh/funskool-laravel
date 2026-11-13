<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CheckoutOptionsController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Channel\CatalogController as ChannelCatalogController;
use App\Http\Controllers\Channel\OrderController as ChannelOrderController;
use App\Http\Controllers\Channel\SupportTicketController as ChannelSupportTicketController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Http\Controllers\Webhooks\ShiprocketWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer API (Sanctum tokens) — for a first-party React/Next/mobile app
|--------------------------------------------------------------------------
*/

Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink']);
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);
});

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category:slug}', [CategoryController::class, 'show']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product:slug}', [ProductController::class, 'show']);
Route::get('/products/{product:slug}/reviews', [ReviewController::class, 'index']);

Route::get('/shipping-methods', [CheckoutOptionsController::class, 'shippingMethods']);
Route::get('/payment-methods', [CheckoutOptionsController::class, 'paymentMethods']);

Route::get('/pages', [PageController::class, 'index']);
Route::get('/pages/{slug}', [PageController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/products/{product:slug}/reviews', [ReviewController::class, 'store']);

    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::patch('/cart/{cartItem}', [CartController::class, 'update']);
    Route::delete('/cart/{cartItem}', [CartController::class, 'destroy']);

    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout');

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{order}/returns', [OrderController::class, 'requestReturn']);
    Route::get('/orders/{order}/payment-link', [OrderController::class, 'paymentLink']);

    Route::get('/support-tickets', [SupportTicketController::class, 'index']);
    Route::post('/support-tickets', [SupportTicketController::class, 'store'])->middleware('throttle:support');
    Route::get('/support-tickets/{ticket}', [SupportTicketController::class, 'show']);
    Route::post('/support-tickets/{ticket}/messages', [SupportTicketController::class, 'reply'])->middleware('throttle:support');

    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/{product}', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy']);

    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Channel API v1 — connect ANY external website (server-to-server)
|--------------------------------------------------------------------------
| Auth: "Authorization: Bearer <channel api key>" (Admin → Integrations →
| Sales Channels). Full reference: docs/CHANNEL_API.md
*/

Route::prefix('channel/v1')
    ->middleware(['channel.auth', 'throttle:channel'])
    ->name('channel.')
    ->group(function () {
        Route::get('/store', [ChannelCatalogController::class, 'store']);
        Route::get('/categories', [ChannelCatalogController::class, 'categories']);
        Route::get('/brands', [ChannelCatalogController::class, 'brands']);
        Route::get('/products', [ChannelCatalogController::class, 'products']);
        Route::get('/products/{sku}', [ChannelCatalogController::class, 'product']);
        Route::get('/inventory', [ChannelCatalogController::class, 'inventory']);
        Route::get('/shipping-methods', [ChannelCatalogController::class, 'shippingMethods']);
        Route::get('/payment-methods', [ChannelCatalogController::class, 'paymentMethods']);
        Route::get('/pages', [ChannelCatalogController::class, 'pages']);
        Route::get('/pages/{slug}', [ChannelCatalogController::class, 'page']);

        Route::post('/quote', [ChannelOrderController::class, 'quote']);
        Route::get('/orders', [ChannelOrderController::class, 'index']);
        Route::post('/orders', [ChannelOrderController::class, 'store']);
        Route::get('/orders/{orderNumber}', [ChannelOrderController::class, 'show']);
        Route::post('/orders/{orderNumber}/cancel', [ChannelOrderController::class, 'cancel']);

        Route::post('/support-tickets', [ChannelSupportTicketController::class, 'store']);
        Route::get('/support-tickets/{ticketNumber}', [ChannelSupportTicketController::class, 'show']);
        Route::post('/support-tickets/{ticketNumber}/messages', [ChannelSupportTicketController::class, 'reply']);
    });

/*
|--------------------------------------------------------------------------
| Inbound provider webhooks (signature-verified, idempotent)
|--------------------------------------------------------------------------
*/

Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)->name('webhooks.payments');
Route::post('/webhooks/shipping/shiprocket', ShiprocketWebhookController::class)->name('webhooks.shiprocket');
