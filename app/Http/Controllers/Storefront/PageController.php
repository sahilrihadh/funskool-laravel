<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Page;

class PageController extends Controller
{
    public function __invoke(string $slug)
    {
        return view('storefront.page', [
            'page' => Page::query()->published()->where('slug', $slug)->firstOrFail(),
        ]);
    }
}
