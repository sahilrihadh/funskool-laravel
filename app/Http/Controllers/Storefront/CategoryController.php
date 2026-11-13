<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    public function __invoke(Category $category)
    {
        return view('storefront.category', [
            'category' => $category,
            'products' => $category->products()->where('is_active', true)->paginate(12),
        ]);
    }
}
