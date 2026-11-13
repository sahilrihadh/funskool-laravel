<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;

class HomeController extends Controller
{
    public function __invoke()
    {
        return view('storefront.home', [
            'banners' => Banner::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'categories' => Category::query()->where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->get(),
            'featuredProducts' => Product::query()->where('is_active', true)->where('is_featured', true)->latest()->take(8)->get(),
        ]);
    }
}
