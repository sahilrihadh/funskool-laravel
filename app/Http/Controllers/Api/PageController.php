<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;

class PageController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Page::query()->published()->orderBy('sort_order')->get(['title', 'slug', 'show_in_footer'])]);
    }

    public function show(string $slug)
    {
        return response()->json(['data' => Page::query()->published()->where('slug', $slug)->firstOrFail()
            ->only(['title', 'slug', 'content', 'meta_title', 'meta_description', 'updated_at'])]);
    }
}
