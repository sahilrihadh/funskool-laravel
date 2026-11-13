<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\AddressController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\PaymentController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\ReviewController;
use App\Http\Controllers\Storefront\SitemapController;
use App\Http\Controllers\Storefront\SupportController;
use App\Http\Controllers\Storefront\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/categories/{category:slug}', CategoryController::class)->name('categories.show');
Route::get('/products/{product:slug}', ProductController::class)->name('products.show');
Route::get('/pages/{slug}', PageController::class)->name('pages.show');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('guest')->group(function () {
    Route::get('/password/forgot', [PasswordResetController::class, 'showRequest'])->name('password.request');
    Route::post('/password/forgot', [PasswordResetController::class, 'sendLink'])->middleware('throttle:auth')->name('password.email');
    Route::get('/password/reset/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:auth')->name('password.update');
});

// Hosted payment redirects (customers of the storefront AND of connected websites).
Route::get('/pay/{order:order_number}', [PaymentController::class, 'start'])->middleware('signed')->name('payments.start');
Route::get('/payments/{gateway}/return/{order:order_number}', [PaymentController::class, 'return'])->name('payments.return');
Route::get('/payments/cancelled/{order:order_number}', [PaymentController::class, 'cancelled'])->name('payments.cancelled');

// Support: contact form for everyone, token links for guests, attachments behind an access check.
Route::get('/contact', [SupportController::class, 'contact'])->name('support.contact');
Route::post('/contact', [SupportController::class, 'store'])->middleware('throttle:support')->name('support.store');
Route::get('/support/tickets/{ticket}', [SupportController::class, 'guest'])->name('support.guest');
Route::post('/support/tickets/{ticket}/reply', [SupportController::class, 'guestReply'])->middleware('throttle:support')->name('support.guest.reply');
Route::get('/support/attachments/{message}/{index}', [SupportController::class, 'attachment'])->whereNumber('index')->name('support.attachment');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');

Route::middleware('auth')->group(function () {
    Route::patch('/cart/{cartItem}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');
});

Route::post('/products/{product:slug}/reviews', [ReviewController::class, 'store'])
    ->middleware('auth')
    ->name('reviews.store');

Route::middleware('auth')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
    Route::get('/checkout/{order}/confirmation', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');

    Route::get('/account/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/account/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/account/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/account/orders/{order}/return', [OrderController::class, 'requestReturn'])->name('orders.return');
    Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');

    Route::get('/account/support', [SupportController::class, 'index'])->name('support.index');
    Route::get('/account/support/{ticket}', [SupportController::class, 'show'])->name('support.show');
    Route::post('/account/support/{ticket}/reply', [SupportController::class, 'reply'])->middleware('throttle:support')->name('support.reply');

    Route::get('/account/profile', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('/account/profile', [AccountController::class, 'update'])->name('account.update');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');

    Route::get('/account/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/account/wishlist/{product}', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('/account/wishlist/{product}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    Route::get('/account/addresses', [AddressController::class, 'index'])->name('addresses.index');
    Route::post('/account/addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::delete('/account/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
});
