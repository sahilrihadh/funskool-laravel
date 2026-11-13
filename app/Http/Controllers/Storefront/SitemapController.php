<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;

class SitemapController extends Controller
{
    public function __invoke()
    {
        $urls = collect([['loc' => route('home'), 'lastmod' => now()]])
            ->concat(Category::query()->where('is_active', true)->get()->map(fn ($category) => ['loc' => route('categories.show', $category), 'lastmod' => $category->updated_at]))
            ->concat(Product::query()->where('is_active', true)->get(['slug', 'updated_at'])->map(fn ($product) => ['loc' => route('products.show', $product), 'lastmod' => $product->updated_at]))
            ->concat(Page::query()->published()->get(['slug', 'updated_at'])->map(fn ($page) => ['loc' => route('pages.show', $page->slug), 'lastmod' => $page->updated_at]));

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
